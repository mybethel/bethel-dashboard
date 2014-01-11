<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster/PodcasterController
 */

namespace Drupal\bethel_podcaster;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
use Drupal\bethel_podcaster\VimeoParser;
use Drupal\bethel_podcaster\BethelParser;

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
      array('data' => 'Title', 'class' => array('title', 'text-muted')),
      array('data' => 'Type', 'class' => array('type', 'text-muted')),
      array('data' => 'Subscribers', 'class' => array('subscribers', 'text-muted')),
      '',
    );
    
    $podcast_row = array();
    
    $query = \Drupal::entityQuery('node');
    $result = $query
      ->condition('type', 'podcast')
      ->condition('uid', $user->id())
      ->execute();
    
    $podcasts = entity_load_multiple('node', $result);
    
    foreach ($podcasts as $podcast) {
      $podcast_image = field_view_field($podcast, 'field_image', 'teaser');
      $podcast_row[] = array(
        array('data' => drupal_render($podcast_image), 'class' => 'thumb'),
        array('data' => l($podcast->getTitle(), '/node/' . $podcast->id()), 'class' => 'title'),
        array('data' => $podcast->get('field_type')->value, 'class' => 'type'),
        array('data' => '<span data-toggle="tooltip" data-placement="bottom" title="The average number of daily subscribers over the past 7 days.">' . $this->getSubscribers($podcast->id()) . '</span>', 'class' => 'subscribers'),
        array('data' => l('Edit', '/node/' . $podcast->id() . '/edit', array('attributes' => array('class' => array('btn', 'btn-default', 'btn-sm')))) . ' ' . l('Delete', '/node/' . $podcast->id() . '/delete', array('attributes' => array('class' => array('btn', 'btn-danger', 'btn-sm')))), 'class' => 'operations'),
      );
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
    
    drupal_add_js('jQuery(document).ready(function () { jQuery("td.subscribers span").tooltip() });', 'inline');

    return l('New Podcast', '/node/add/podcast', array('attributes' => array('class' => array('add-btn', 'btn', 'btn-primary', 'pull-right')))) . theme_table($podcast_table);
  }
  
  public function getSubscribers($node) {
    $cache = cache('bethel.podcaster');
    $subscribers = $cache->get('subscribers_' . $node);

    if (!$subscribers) {
      $this->analyticsConfirmToken();
      
      if (!$this->analytics->getAccessToken()) {
        print $this->analytics->createAuthUrl();
      } else {
        $analytics = new \Google_AnalyticsService($this->analytics);
        $visits = $analytics->data_ga->get(
          'ga:79242714',
          date('Y-m-d', mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"))),
          date('Y-m-d'),
          'ga:pageviews',
          array('dimensions' => 'ga:pagePath', 'filters' => 'ga:pagePath==/node/' . $node . '/podcast.xml'));
        
        $subscribers = round($visits->getRows()[0][1]/7);
        $cache->set('subscribers_' . $node, $subscribers, time()+(24*60*60));
      }  
    } else {
      $subscribers = $subscribers->data;
    }
    
    return $subscribers;
  }
  
  public function analyticsConnect() {
    $this->analytics->authenticate();
    $access_token = $this->analytics->getAccessToken();
    
    $config = \Drupal::config('bethel.gapi');
    $config->set('access_token', $access_token)->save();
    
    return new RedirectResponse(\Drupal::url('bethel_podcaster.podcast_admin'));; 
  }
  
  private function analyticsConfirmToken() {
    $config = \Drupal::config('bethel.gapi');
    $access_token = $config->get('access_token');
    
    if ($access_token) {
      $this->analytics->setAccessToken($access_token);
    }
    
    if ($this->analytics->isAccessTokenExpired() && $credentials['refresh_token']) {
      $credentials = \Drupal\Component\Utility\Json::decode($access_token);
      $this->analytics->refreshToken($credentials['refresh_token']);
    } else {
      print $this->analytics->createAuthUrl();
    }
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
    
    if ($node->field_type->value == "Vimeo") {
      $tags = $node->field_tags->getValue();
      $vimeo = new VimeoParser(array('content' => array('#videofeed' => $vimeo_username, '#filtered' => $tags)));
      $podcast['items'] = $vimeo->variables['videos'];
      $podcast['type'] = 'video';
    } else {
      $bethel = new BethelParser('http://coastal.getbethel.com');
      $podcast['items'] = $bethel->variables['podcast'];
      $podcast['type'] = 'video';
    }

    // Build the XML file from the template.
    $podcast_xml = twig_render_template(drupal_get_path('module', 'bethel_podcaster') . '/templates/' . $podcast['type'] . '-podcast.html.twig', $podcast);
    
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
