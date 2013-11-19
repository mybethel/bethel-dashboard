<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\VimeoParser
 */

namespace Drupal\bethel_podcaster;

class VimeoParser {

  private $username;
  private $tags;
  
  public $variables;

  // Read the Google Analytics UUID or create a new one.
  public function __construct($variables) {
    $this->variables = $variables;
    $this->username = $variables['content']['#videofeed'];
    $this->tags = $variables['content']['#filtered'];
    
    $page = 1;
    do {
      $this->processVimeoFeed($page);
      $page++;
    } while ($page <= 3);
  }
  
  private function processVimeoFeed($page) {
    $rawdata = file_get_contents('http://vimeo.com/api/v2/' . $this->username . '/videos.json?page=' . $page);
    
    $matching_tags = array();
    
    foreach ($this->tags as $tag) {
      $tag_entity = entity_load('taxonomy_term', $tag['target_id']);
      $matching_tags[] = $tag_entity->getValue()['name'][0]['value'];
    }
  
    // Decode the JSON into an array for parsing.
    $videos = \Drupal\Component\Utility\Json::decode($rawdata);
    $config = \Drupal::config('bethel.podcaster');
  
    // Evaluate each video that Vimeo returns for the user.
    foreach ($videos as $video) {
      $tags = explode(', ', $video['tags']);
  
      foreach ($tags as $tag) {
        // Only include videos in the podcast that match tags the user has set.
        if (in_array($tag, $matching_tags)) {
          $durationformat = $video['duration'] < 3600 ? 'i:s' : 'H:i:s';
          $this->variables['videos'][$video['id']]['title'] = $video['title'];
          $this->variables['videos'][$video['id']]['url'] = $video['url'];
          $this->variables['videos'][$video['id']]['description'] = $video['description'];
          $this->variables['videos'][$video['id']]['length'] = $video['duration'];
          $this->variables['videos'][$video['id']]['thumbnail'] = $video['thumbnail_small'];
          $this->variables['videos'][$video['id']]['duration'] = date($durationformat, $video['duration']);
          $this->variables['videos'][$video['id']]['video'] = $config->get('video_url.' . $video['id']);
          $this->variables['videos'][$video['id']]['form'] = drupal_get_form('bethel_podcaster_video_form_' . $video['id'], $video['id']);
        }
      }
    }
  }
}
