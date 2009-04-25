<?php
/*
 * phtagr.
 * 
 * Multi-user image gallery.
 * 
 * Copyright (C) 2006-2009 Sebastian Felis, sebastian@phtagr.org
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2 of the 
 * License.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class CommentsController extends AppController 
{
  var $name = 'Comments';
  var $uses = array('Comment', 'Media');
  var $helpers = array('Html', 'Form', 'Rss');
  var $components = array('Query', 'Captcha', 'Email');

  function beforeFilter() {
    parent::beforeFilter();

    $this->Query->parseArgs();
  }

  function view($id = null) {
    if (!$id) {
      $this->Session->setFlash(__('Invalid Comment.', true));
      $this->redirect(array('action'=>'index'));
    }
    $this->set('comment', $this->Comment->read(null, $id));
  }

  function add() {
    if (!empty($this->data) && isset($this->data['Media']['id'])) {
      $mediaId = intval($this->data['Media']['id']);
      $url = $this->Query->getUrl();
      $user = $this->getUser();
      $userId = $this->getUserId();
      $role = $this->getUserRole();

      // Fetch required authentication method
      if ($role < ROLE_GUEST) {
        $auth = (COMMENT_AUTH_NAME | COMMENT_AUTH_CAPTCHA);
      } elseif ($role <= ROLE_GUEST) {
        $auth = $this->getOption('comment.auth', COMMENT_AUTH_NONE);
      } else {
        $auth = COMMENT_AUTH_NONE;
      }

      // Check capatcha if required
      if (($auth & COMMENT_AUTH_CAPTCHA) > 0 && (!$this->Session->check('captcha') || $this->data['Captcha']['verification'] != $this->Session->read('captcha'))) {
        $this->Session->setFlash("Verification failed");
        $this->Logger->warn("Captcha verification failed: ".$this->data['Captcha']['verification']." != ".$this->Session->read('captcha'));
        $this->Session->delete('captcha');
        $this->Session->write('Comment.data', $this->data);
        $this->Session->write('Comment.validationErrors', $this->Comment->validationErrors);
        $this->redirect("/images/view/$mediaId/$url"); 
      }
      $this->Session->delete('captcha');

      // Get media and check permissons
      $media = $this->Media->findById($mediaId);
      if (!$media) {
        $this->Session->setFlash("Media not found");
        $this->Logger->info("Media $mediaId not found");
        $this->redirect("/explorer");
      }
      if (!$this->Media->checkAccess($media, $user, ACL_READ_PREVIEW, ACL_READ_MASK)) {
        $this->Session->setFlash("Media not found");
        $this->Logger->info("Comments denied to media $mediaId");
        $this->redirect("/explorer");
      }

      $this->Comment->create();
      $this->data['Comment']['media_id'] = $mediaId;
      $this->data['Comment']['date'] = date("Y-m-d H:i:s", time());
      uses('Sanitize');
      $this->data['Comment']['text'] = Sanitize::html($this->data['Comment']['text']);
      if (($auth & COMMENT_AUTH_NAME) == 0) {
        $this->data['Comment']['user_id'] = $user['User']['id'];
        $this->data['Comment']['name'] = $user['User']['username'];
        $this->data['Comment']['email'] = $user['User']['email'];
      }
      if ($this->Comment->save($this->data)) {
        $commentId = $this->Comment->getLastInsertID();
        $this->Session->setFlash(__('The Comment has been saved', true));
        $this->Logger->info("New comment of media $mediaId");
        // Send email notification of other media owners
        if ($media['Media']['user_id'] != $userId) {
          $this->_sendEmail($commentId);
        }
        $this->_sendNotifies($mediaId, $commentId);
      } else {
        $this->Session->setFlash(__('The Comment could not be saved. Please, try again.', true));
        $this->Logger->err("Could not save comment to media $mediaId");
        $this->Logger->trace($this->Comment->validationErrors);
        $this->Session->write('Comment.data', $this->data);
        $this->Session->write('Comment.validationErrors', $this->Comment->validationErrors);
      }
      $this->redirect("/images/view/$mediaId/$url");
    } else {
      $this->redirect("/explorer");
    }
  }

  function _sendEmail($commentId) {
    $comment = $this->Comment->findById($commentId);
    if (!$comment) {
      $this->Logger->err("Could not find comment $commentId");
      return;
    }
    $user = $this->User->findById($comment['Media']['user_id']);
    if (!$user) {
      $this->Logger->err("Could not find user '{$comment['Media']['user_id']}'");
      return;
    }
    $email = $user['User']['email'];
    
    $this->Email->to = sprintf("%s %s <%s>",
      $user['User']['firstname'],
      $user['User']['lastname'],
      $user['User']['email']);

    $this->Email->subject = 'New Comment of Media '.$comment['Media']['name'];
    $this->Email->replyTo = 'noreply@phtagr.org';
    $this->Email->from = 'phTagr <noreply@phtagr.org>';

    $this->Email->template = 'comment';
    $this->set('user', $user);
    $this->set('data', $comment);

    if (!$this->Email->send()) {
      $this->Logger->warn("Could not send notification mail for new comment");
    } else {
      $this->Logger->info("Notification mail for new comment send to {$user['User']['email']}");
    }
  }

  /** Send email notifications to previous commentator which enables the mail
   * notification. It collects all emails of previous commentators who accepted
   * a notification mail
    @param mediaId Current media id
    @param commentId Id of the new comment */
  function _sendNotifies($mediaId, $commentId) {
    $this->Media->bindModel(array('hasMany' => array('Comment')));
    $media = $this->Media->findById($mediaId);
    if (!$media) {
      $this->Logger->err("Could not find media $mediaId");
      return;
    }
    $comment = $this->Comment->findById($commentId); 
    if (!$comment || $comment['Comment']['media_id'] != $mediaId) {
      $this->Logger->err("Could not find comment $commentId");
      return;
    } elseif ($comment['Comment']['media_id'] != $mediaId) {
      $this->Logger->err("Comment $commentId does not corrolate with media $mediaId");
      return;
    }

    $emails = array();
    foreach($media['Comment'] as $c) {
      // not media owner, disabled notify, current comment
      if ($c['user_id'] == $media['Media']['user_id'] || 
        !$c['notify'] ||
        $c['id'] == $commentId) {
        continue;
      }
      $emails[] = $c['email'];
    }
    if (!count($emails)) {
      $this->Logger->debug("No user for comment update notifications found");
      return;
    }

    $emails = array_unique($emails);
    $to = array_pop($emails);
    $this->Email->to = $to;
    $this->Email->bcc = $emails;

    $this->Email->subject = 'Comment notification of Media '.$media['Media']['name'];
    $this->Email->replyTo = 'noreply@phtagr.org';
    $this->Email->from = 'phTagr <noreply@phtagr.org>';

    $this->Email->template = 'commentnotify';
    $this->set('data', $comment);
    $this->Logger->debug($comment);
    if (!$this->Email->send()) {
      $this->Logger->warn("Could not send comment update notification mail for new comment");
    } else {
      $this->Logger->info("Send comment update notification to: $to, bbc to: ".implode(', ', $emails));
    }
  }

  function delete($id = null) {
    if (!$id) {
      $this->Session->setFlash(__('Invalid id for Comment', true));
      $this->redirect("/explorer");
    }
    $this->requireRole(ROLE_USER, array('loginRedirect' => '/comments/delete/'.$id));

    $comment = $this->Comment->findById($id);
    if (!$comment) {
      $this->redirect("/explorer");
    }
    $userId = $this->getUserId();

    // Allow only comment owner, media owner or admins
    if ((isset($comment['User']['id']) && $comment['User']['id'] == $userId) || ($comment['Media']['user_id'] == $userId) || ($this->getUserRole() == ROLE_ADMIN)) {
      if ($this->Comment->del($id)) {
        $this->Session->setFlash(__('Comment deleted', true));
        $this->Logger->info("Delete comment {$comment['Comment']['id']} of media {$comment['Media']['id']}");
      }
    } else {
      $this->Session->setFlash("Deny deletion of comment");
      $this->Logger->warn("Deny deletion of comment");
    }
    $url = $this->Query->getUrl();
    $this->redirect("/images/view/".$comment['Media']['id']."/$url");
  }

  function captcha() {
    $this->Captcha->render(); 
  }

  function rss() {
    $this->layoutPath = 'rss';
    $where = '1 = 1'.$this->Media->buildWhereAcl($this->getUser());
    $comments = $this->Comment->findAll($where, null, 'Comment.date DESC', 20);
    $this->set('data', $comments);

    if (Configure::read('debug') > 1) {
      Configure::write('debug', 1);
    }
    $this->set(
        'channel', array(
          'title' => "New Comments",
          'link' => "/comments/rss",
          'description' => "Recently Published Comments" )
        );
  }
}
?>