<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" >
<channel>
  <title>phTagr Media RSS</title>
  <link><?php echo Router::url('/', true); ?></link>
  <description>Media RSS of phTagr</description>
<?php if ($query->hasPrev()): ?>
  <atom:link rel="previous" href="<?php echo Router::url($query->getPrevUrl('/explorer/media/').'/media.rss', true); ?>" />
<?php endif; ?>
<?php if ($query->hasNext()): ?>
  <atom:link rel="next" href="<?php echo Router::url($query->getNextUrl('/explorer/media/').'/media.rss', true); ?>" />
<?php endif; ?>
<?php
  $query->initialize(); 
  if ($session->check('Authentication.key')) {
    $optParams = 'key:'.$session->read('Authentication.key').'/';
  } else {
    $optParams = '';
  }
?>
<?php foreach ($this->data as $media): ?>
  <item>
    <title><?php echo $media['Media']['name']; ?></title>
    <link><?php 
      $url = '/images/view/'.$media['Media']['id'].'/'.$optParams;
      if ($query->getParams()) {
        $url .= $query->getParams().'/';
      }
      echo Router::url($url, true); ?></link>
    <?php 
      $thumbSize = $imageData->getimagesize($media, OUTPUT_SIZE_THUMB);
      $previewSize = $imageData->getimagesize($media, OUTPUT_SIZE_PREVIEW);
      $thumbUrl = '/media/thumb/'.$media['Media']['id'].'/'.$optParams.$media['Media']['file'];
      if ($media['Media']['canReadOriginal']) {
        $contentUrl = '/media/high/'.$media['Media']['id'].'/'.$optParams.$media['Media']['file'];
      } else {
        $contentUrl = '/media/preview/'.$media['Media']['id'].'/'.$optParams.$media['Media']['file'];
      }
    ?>
    <media:thumbnail url="<?php echo Router::url($thumbUrl, true); ?>" <?php echo $thumbSize[3]; ?> />
    <media:content url="<?php echo Router::url($contentUrl, true); ?>" <?php echo $previewSize[3]; ?> />
    <guid><?php echo Router::url($url, true); ?></guid>
    <description type="html" />
  </item>
<?php endforeach; ?>
</channel>
</rss> 