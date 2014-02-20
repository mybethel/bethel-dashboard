<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\VimeoParser
 */

namespace Drupal\bethel_podcaster;

use \Drupal\Component\Utility\Json;
use Guzzle\Http\Client;

class VimeoParser {

  private $username;
  private $id;

  public $variables;

  // Read the Google Analytics UUID or create a new one.
  public function __construct($variables) {
    $this->variables = $variables;

    $this->id = $variables['id'];

    $this->processVimeoFeed();
  }

  private function processVimeoFeed() {
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->get('podcast/all/' . $this->id);
    $media = $request->send()->json();
    
    foreach ($media as $index => $item) {
      $index = strtotime($item['date']) . '.' . $index;

      $durationformat = $item['duration'] < 3600 ? 'i:s' : 'H:i:s';
      $this->variables['videos'][$index]['uuid'] = $item['_id'];
      $this->variables['videos'][$index]['title'] = htmlspecialchars($item['title']);
      $this->variables['videos'][$index]['date'] = date('r', strtotime($item['date']));
      $this->variables['videos'][$index]['description'] = htmlspecialchars($item['description']);
      $this->variables['videos'][$index]['keywords'] = htmlspecialchars(implode(', ', $item['tags']));
      $this->variables['videos'][$index]['thumbnail'] = $item['thumbnail'];
      $this->variables['videos'][$index]['duration'] = date($durationformat, $item['duration']);
      $this->variables['videos'][$index]['resource']['url'] = isset($item['url']) ? $item['url'] : '';
      $this->variables['videos'][$index]['resource']['size'] = isset($item['size']) ? $item['size'] : '';
      $this->variables['videos'][$index]['resource']['type'] = 'video/mp4';
      $this->variables['videos'][$index]['form'] = drupal_get_form('bethel_podcaster_video_form_' . $item['_id'], $item['_id']);
    }
    
    krsort($this->variables['videos']);
  }
}
