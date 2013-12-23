<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\BethelParser
 */

namespace Drupal\bethel_podcaster;

class BethelParser {

  private $domain;
  
  public $variables;

  public function __construct($domain) {
    $this->domain = $domain;
    $this->processBethelFeed();
  }
  
  private function processBethelFeed() {
    $rawdata = file_get_contents($this->domain . '/bethel/podcaster.json');
  
    // Decode the JSON into an array for parsing.
    $items = \Drupal\Component\Utility\Json::decode($rawdata);
    $config = \Drupal::config('bethel.podcaster');
  
    // Evaluate each video that Bethel returns.
    foreach ($items['podcasts'] as $index => $item) {
      //$durationformat = $video['duration'] < 3600 ? 'i:s' : 'H:i:s';
      $this->variables['podcast'][$index]['title'] = htmlspecialchars($item['podcast']['title']);
      $this->variables['podcast'][$index]['url'] = $item['podcast']['field_audio'];
      $this->variables['podcast'][$index]['date'] = $item['podcast']['created'];
      $this->variables['podcast'][$index]['description'] = $item['podcast']['body'];
      $this->variables['podcast'][$index]['keywords'] = ''; //htmlspecialchars($video['tags']);
      $this->variables['podcast'][$index]['length'] = ''; //$video['duration'];
      $this->variables['podcast'][$index]['thumbnail'] = ''; //$video['thumbnail_small'];
      $this->variables['podcast'][$index]['duration'] = ''; //date($durationformat, $video['duration']);
      $this->variables['podcast'][$index]['resource']['url'] = $item['podcast']['field_audio'];
      $this->variables['podcast'][$index]['resource']['size'] = $item['podcast']['filesize'];
      $this->variables['podcast'][$index]['resource']['type'] = 'audio/mp3';
    }
  }
}
