<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\BethelParser
 */

namespace Drupal\bethel_podcaster;

require_once('/var/www/vendor/autoload.php');

use Aws\S3\S3Client;
use Guzzle\Http\Client;

class BethelParser {

  private $user;
  private $id;
  private $s3;

  public $variables;

  public $policy;
  public $signature;

  public function __construct($variables) {
    $this->variables = $variables;

    $this->username = $variables['user'];
    $this->id = $variables['id'];

    $this->s3 = S3Client::factory(array(
      'key' => $_ENV['S3']['key'],
      'secret' => $_ENV['S3']['secret']
    ));

    $policy_json = '{"expiration": "2014-12-31T00:00:00Z","conditions": [ {"bucket": "cloud.bethel.io"}, ["starts-with", "$key", "' . $this->username . '/' . $this->id . '"],{"acl": "public-read"},{"success_action_redirect": "http://my.bethel.io/node/' . $this->id . '"},["starts-with", "$Content-Type", "audio/"]]}';

    $this->policy = base64_encode($policy_json);
    $this->signature = base64_encode(hash_hmac('sha1', $this->policy, $_ENV['S3']['secret'], TRUE));

    $this->processBethelFeed();
  }

  private function processBethelFeed() {
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->get('podcast/all/' . $this->id);
    $media = $request->send()->json();
    
    foreach ($media as $index => $item) {
      $index = strtotime($item['date']) . '.' . $index;
      $filename = explode('/', $item['url']);
      $filename = $filename[sizeof($filename) - 1];
      
      $title = ($item['title']) ? : $filename;

      $this->variables['podcast'][$index]['uuid'] = $item['_id'];
      $this->variables['podcast'][$index]['title'] = htmlspecialchars($title);
      $this->variables['podcast'][$index]['date'] = date('r', strtotime($item['date']));
      $this->variables['podcast'][$index]['description'] = htmlspecialchars($item['description']);
      $this->variables['podcast'][$index]['keywords'] = ''; //htmlspecialchars($video['tags']);
      $this->variables['podcast'][$index]['duration'] = $item['duration'];
      $this->variables['podcast'][$index]['resource']['url'] = str_replace(array('%2F', '%3A'), array('/', ':'), rawurlencode($item['url']));
      $this->variables['podcast'][$index]['resource']['size'] = $item['size'];
      $this->variables['podcast'][$index]['resource']['type'] = 'audio/mp3';
    }

    krsort($this->variables['podcast']);
  }
}
