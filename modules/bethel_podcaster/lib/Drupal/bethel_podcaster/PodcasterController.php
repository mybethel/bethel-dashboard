<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster/PodcasterController
 */

namespace Drupal\bethel_podcaster;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Json;
use Drupal\Component\Utility\Settings;
use Drupal\Core\Config\Config;

class PodcasterController implements ContainerInjectionInterface {

  /**
   * The system.theme config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a ThemeController object.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config.
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('bethel.podcaster')
    );
  }

  /**
   * Generates a Podcast.
   *
   * @param integer $id
   *   The node ID to built a podcast for.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The share counts as json.
   */
  public function podcast_feed($id) {    
    // Validate the widget is valid, and the node ID is an actual node.
    if (!is_numeric($id) || !$node = node_load($id)) {
      throw new NotFoundHttpException();
    }
    
    global $base_url;
    $author = user_load($node->getValue()['uid'][0]['target_id']);
    $image = entity_load('file', $node->getValue()['field_image'][0]['target_id']);
    
    // Build the Podcast information from the Node.
    $podcast = array();
    
    $podcast['title'] = $node->getValue()['title'];
    $podcast['description'] = $node->getValue()['body'][0]['value'];
    $podcast['short_description'] = $node->getValue()['body'][0]['summary'];
    $podcast['website'] = $base_url . '/' . node_uri($node)['path'];
    $podcast['author'] = $author->getValue()['field_name'][0]['value'];
    $podcast['author_email'] = $author->getValue()['mail'][0]['value'];
    $podcast['feed'] = $base_url . '/' . node_uri($node)['path'] . '/podcast.xml';
    $podcast['image'] = file_create_url($image->getValue()['uri'][0]['value']);
    $podcast['copyright'] = $node->getValue()['field_copyright'][0]['value'];
    
    $vimeo_username = $node->getValue()['field_vimeo'][0]['value'];
    
    // Get all the tags that we associate with this podcast.
    $tag_field = $node->getValue()['field_tags'];
    $matching_tags = array();
    
    foreach ($tag_field as $tag) {
      $tag_entity = entity_load('taxonomy_term', $tag['target_id']);
      $matching_tags[] = $tag_entity->values['name']['und'];
    }
    
    $rawdata = file_get_contents('http://vimeo.com/api/v2/' . $vimeo_username . '/videos.json');
    
    // Decode the JSON into an array for parsing.
    $videos = Json::decode($rawdata);
    
    // Evaluate each video that Vimeo returns for the user.
    foreach ($videos as $video) {
      $tags = explode(', ', $video['tags']);
      
      foreach ($tags as $tag) {
        // Only include videos in the podcast that match tags the user has set.
        if (in_array($tag, $matching_tags)) {
          $durationformat = $video['duration'] < 3600 ? 'i:s' : 'H:i:s';
          $podcast['items'][$video['id']]['title'] = $video['title'];
          $podcast['items'][$video['id']]['date'] = date(DATE_RSS, strtotime($video['upload_date']));;
          $podcast['items'][$video['id']]['link'] = $video['url'];
          $podcast['items'][$video['id']]['description'] = $video['description'];
          $podcast['items'][$video['id']]['length'] = $video['duration'];
          $podcast['items'][$video['id']]['keywords'] = $video['tags'];
          $podcast['items'][$video['id']]['image'] = $video['thumbnail_large'];
          $podcast['items'][$video['id']]['duration'] = date($durationformat, $video['duration']);
          $podcast['items'][$video['id']]['video'] = $this->config->get('video_url.' . $video['id']);
          $podcast['items'][$video['id']]['video_size'] = $this->config->get('video_size.' . $video['id']);
        }
      }
    }

    // Build the XML file from the template.
    $podcast_xml = twig_render_template(drupal_get_path('module', 'bethel_podcaster') . '/templates/video-podcast.html.twig', $podcast);
    
    $headers = array(
      'Content-Length' => strlen($podcast_xml),
      'Content-Type' => 'text/xml'
    );

    // Return a JSON formatted array.
    return new Response($podcast_xml, 200, $headers);
  }
}
