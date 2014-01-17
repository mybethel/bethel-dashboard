<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\BethelParser
 */

namespace Drupal\bethel_podcaster;

require_once('/var/www/vendor/autoload.php');

use Aws\S3\S3Client;

class BethelParser {

  private $user;
  private $id;
  private $s3;
  
  public $variables;

  public function __construct($variables) {
    $this->variables = $variables;
    
    $this->username = $variables['user'];
    $this->id = $variables['id'];
    
    $this->s3 = S3Client::factory(array(
      'key'    => $_ENV['S3']['key'],
      'secret' => $_ENV['S3']['secret']
    ));
    
    $this->processBethelFeed();
  }
  
  private function processBethelFeed() {
    $config = \Drupal::config('bethel.podcaster');
    
    // Podcasts are stored in a bucket with the username and node ID.
    $podcast_files = $this->s3->getIterator('ListObjects', array(
      'Bucket' => 'bethel-podcaster',
      'Prefix' => $this->username . '/' . $this->id . '/'
    ));
    
    // Evaluate each video that is stored in Bethel.
    foreach ($podcast_files as $index => $item) {
      if ($item['Size'] <= 0)
        continue;
        
      $date = $config->get('bethel.' . trim($item['ETag'], '"') . '.date') ?: date('r', strtotime($item['LastModified']));
      $index = strtotime($date) . '.' . $index;
      $filename = explode('/', $item['Key']);
      $filename = $filename[sizeof($filename)-1];
      $filepath = str_replace($filename, '', $item['Key']) . rawurlencode($filename);

      $this->variables['podcast'][$index]['uuid'] = trim($item['ETag'], '"');
      $this->variables['podcast'][$index]['title'] = htmlspecialchars($config->get('bethel.' . trim($item['ETag'], '"') . '.title'));
      $this->variables['podcast'][$index]['url'] = 'http://bethel-podcaster.s3-website-us-east-1.amazonaws.com/' . $filepath;
      $this->variables['podcast'][$index]['date'] = $date;
      $this->variables['podcast'][$index]['description'] = htmlspecialchars($config->get('bethel.' . trim($item['ETag'], '"') . '.description'));
      $this->variables['podcast'][$index]['keywords'] = ''; //htmlspecialchars($video['tags']);
      $this->variables['podcast'][$index]['duration'] = $config->get('bethel.' . trim($item['ETag'], '"') . '.duration');
      $this->variables['podcast'][$index]['resource']['url'] = 'http://bethel-podcaster.s3-website-us-east-1.amazonaws.com/' . $filepath;
      $this->variables['podcast'][$index]['resource']['size'] = $item['Size'];
      $this->variables['podcast'][$index]['resource']['type'] = 'audio/mp3';
    }
    
    krsort($this->variables['podcast']);
  }
}
