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
use Drupal\Core\Cache\MemoryBackendFactory;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\bethel_api\BethelAPITracking;

class PodcasterController implements ContainerInjectionInterface {

  /**
   * The system.theme config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;
  
  protected $googleAccessToken;
  protected $analytics;

  /**
   * Constructs a ThemeController object.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config.
   */
  public function __construct(Config $config) {
    require_once DRUPAL_ROOT . '/libraries/google-api-php-client/src/Google_Client.php';
    require_once DRUPAL_ROOT . '/libraries/google-api-php-client/src/contrib/Google_AnalyticsService.php';
    
    $this->analytics = new \Google_Client();
    $this->analytics->setApplicationName('Bethel');
    $this->analytics->setClientId('484936756559-42lia9gv6vcerodshrgo5i9tm0qtm553.apps.googleusercontent.com');
    $this->analytics->setClientSecret('_ADCIOcQ8wIq1tKnyIVWfnP3');
    $this->analytics->setRedirectUri('http://my.bethel.io/podcaster/analytics/connect');
    $this->analytics->setDeveloperKey('AIzaSyCud0FIGPFdBIe5wafzmG4hMHFfGx8187M');
    $this->analytics->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));
    $this->analytics->setUseObjects(true);
    
    if (isset($_SESSION['token'])) {
      $this->analytics->setAccessToken($_SESSION['token']);
    }
    
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
  
  public function podcast_admin() {
    global $user;

    $podcast_header = array(
      '',
      array('data' => 'Title', 'field' => 'title', 'sort' => 'desc'),
      array('data' => 'Type', 'field' => 'field_type'),
      'Subscribers',
      'Operations',
    );
    
    $podcast_row = array();
    
    $query = \Drupal::entityQuery('node');
    $result = $query
      ->condition('type', 'podcast')
      ->condition('uid', $user->id())
      ->execute();
    
    $podcasts = entity_load_multiple('node', $result);
    
    foreach ($podcasts as $podcast) {
      $image = entity_load('file', $podcast->get('field_image')->value);
      $podcast_row[] = array(
        l(theme_image_style(array('style_name' => 'thumbnail', 'uri' => $image->uri->value, 'width' => NULL, 'height' => NULL, 'attributes' => NULL)), '/node/' . $podcast->id(), array('html' => TRUE)),
        l($podcast->getTitle(), '/node/' . $podcast->id()),
        $podcast->get('field_type')->value,
        $this->getSubscribers($podcast->id()),
        '');
    }
    
    $podcast_table = array(
      'header' => $podcast_header,
      'rows' => $podcast_row,
      'attributes' => NULL,
      'empty' => 'No podcasts have been created yet.',
      'sticky' => TRUE,
      'caption' => NULL,
      'colgroups' => NULL,
      'responsive' => TRUE,
    );
    
    drupal_set_title('My Podcasts');

    return theme_table($podcast_table);
  }
  
  public function getSubscribers($node) {
    $cache = cache('bethel.podcaster');
    $subscribers = $cache->get('subscribers_' . $node);

    if (!$subscribers) {
      if (!$this->analytics->getAccessToken()) {
        print $this->analytics->createAuthUrl();
      } else {
        $analytics = new \Google_AnalyticsService($this->analytics);
        $visits = $analytics->data_ga->get(
          'ga:79242714',
          date('Y-m-d', mktime(0, 0, 0, date("m") , date("d") - 7, date("Y"))),
          date('Y-m-d'),
          'ga:pageviews',
          array('dimensions' => 'ga:pagePath', 'filters' => 'ga:pagePath==/node/' . $node . '/podcast.xml'));
        
        $subscribers = $visits->getRows()[0][1]/7;
        $cache->set('subscribers_' . $node, $subscribers, time()+(24*60*60));
      }  
    } else {
      $subscribers = $subscribers->data;
    }
    
    return $subscribers;
  }
  
  public function analyticsConnect() {
    $this->analytics->authenticate();
    $_SESSION['token'] = $this->analytics->getAccessToken();
    return $this->analytics->getAccessToken(); 
  }

  /**
   * Generates a Podcast.
   *
   * @param integer $id
   *   The node ID to built a podcast for.
   *
   * @return
   *   The podcast feed in XML optimized for iTunes.
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
    
    $podcast['title'] = $node->title->value;
    $podcast['description'] = $node->body->value;
    $podcast['short_description'] = $node->body->summary;
    $podcast['website'] = $base_url . '/' . node_uri($node)['path'];
    $podcast['author'] = $author->user_name->value;
    $podcast['author_email'] = $author->mail->value;
    $podcast['feed'] = $base_url . '/' . node_uri($node)['path'] . '/podcast.xml';
    $podcast['image'] = file_create_url($image->uri->value);
    $podcast['copyright'] = $node->field_copyright->value;
    
    $vimeo_username = $node->field_vimeo->value;
    
    // Get all the tags that we associate with this podcast.
    $tag_field = $node->field_tags->getValue();
    $matching_tags = array();
    
    foreach ($tag_field as $tag) {
      $tag_entity = entity_load('taxonomy_term', $tag['target_id']);
      $matching_tags[] = $tag_entity->name->value;
    }
    
    $podcast['keywords'] = $matching_tags;
    
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
    
    // Track the podcast load.
    new BethelAPITracking(array(
      'title' => $node->title->value . ' (Podcast Feed)',
      'slug' => node_uri($node)['path'] . '/podcast.xml',
    ));

    // Return a JSON formatted array.
    return new Response($podcast_xml, 200, $headers);
  }
}
