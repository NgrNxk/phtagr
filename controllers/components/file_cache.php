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

class FileCacheComponent extends Object {

  var $controller = null;
  var $components = array('Logger');

  function initialize(&$controller) {
    $this->controller = $controller;
  }

  /** Returns the cache path of the user
    @param userId Id of the current user
    @param mediumId Id of the current image/file
    @param create Creates the directory if true. If false and the directory
    does not exists, it returns false. Default is true.
    @return Path for the cache file. False on error */
  function getPath($userId, $mediumId, $create = true) {
    $userId = intval($userId);
    $mediumId = intval($mediumId);

    $cacheDir = USER_DIR.$userId.DS.'cache'.DS;
    $dir = intval($mediumId/1000);
    $cacheDir .= sprintf("%04d", $dir).DS;

    if (!is_dir($cacheDir)) {
      if (!$create) {
        return false;
      }

      $folder =& new Folder($cacheDir);
      if (!$folder->create($cacheDir)) {
        $this->Logger->err("Could not create cache dir '$cacheDir'");
        return false;
      } else {
        $this->Logger->debug("Create cache dir '$cacheDir'");
      }
    }
    return $cacheDir;
  }

  /** Returns the filename prefix of the cache file
    @param mediumId Id of the current image/file
    @return filename prefix */
  function getFilenamePrefix($mediumId) {
    $mediumId = intval($mediumId);

    $prefix = sprintf("%07d-", $mediumId);
    return $prefix;
  }

  /** Returns the full cache filename prefix of a medium
    @param medium Medium model data
    @param cache filename prefix */
  function getFilename($medium) {
    if (!isset($medium['Medium']['id']) || !isset($medium['Medium']['user_id'])) {
      $this->Logger->err("Precondition failed");
      return false;
    }
    $userId = $medium['Medium']['user_id'];
    $mediumId = $medium['Medium']['id'];

    $path = $this->getPath($userId, $mediumId);
    if (!$path) {
      return false;
    }
    return $path.$this->getFilenamePrefix($mediumId);
  }

  /** Deletes all cached files of a specific image/file.
    @param userId Id of the current user
    @param mediumId Id of the current image/file */
  function delete($userId, $mediumId) {
    $mediumId = intval($mediumId);
    $cacheDir = $this->getPath($userId, $mediumId, false);
    if (!$cacheDir) {
      $this->Logger->trace("No cache dir found for image $mediumId");
      return true;
    }

    $folder =& new Folder($cacheDir);
    $pattern = $this->getFilenamePrefix($mediumId).'.*';
    $files = $folder->find($pattern);
    if ($files) {
      $this->Logger->debug("Delete cached files of image $mediumId");
      foreach($files as $file) {
        $this->Logger->trace("Delete cache file '$file'");
        unlink($folder->addPathElement($cacheDir, $file));
      }
    } else {
      $this->Logger->trace("No cached files found for image $mediumId");
    }
  }

  /** Deletes all cached files of the given user
    @param userId Id of the user */
  function deleteAll($userId) {
    $userId = intval($userId);
    $cacheDir = USER_DIR.$userId.DS.'cache'.DS;
    if (is_dir($cacheDir)) {
      $folder = new Folder();
      $folder->delete($cacheDir);
      $this->Logger->info("Deleted cache dir '$cacheDir'");
    } else {
      $this->Logger->debug("User $userId has no cached files");
    }
  }
}

?>
