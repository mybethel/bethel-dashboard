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
use Guzzle\Http\Client;

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
        array(
          'data' => '<span data-toggle="tooltip" data-placement="bottom" title="The average number of daily subscribers over the past 7 days.">' . $this->getSubscribers($podcast->id()) . '</span>',
          'class' => 'subscribers',
        ),
        array(
          'data' => l('Manage', '/node/' . $podcast->id(), array(
                'attributes' => array(
                  'class' => array(
                    'btn',
                    'btn-default',
                    'btn-sm',
                  )
                )
              )) . ' ' . l('Delete', '/node/' . $podcast->id() . '/delete', array(
                'attributes' => array(
                  'class' => array(
                    'btn',
                    'btn-danger',
                    'btn-sm',
                  )
                )
              )),
          'class' => 'operations',
        ),
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

    //drupal_add_js('jQuery(document).ready(function () { jQuery("td.subscribers span").tooltip() });', 'inline');

    return l('New Podcast', '/node/add/podcast', array(
      'attributes' => array(
        'class' => array(
          'add-btn',
          'btn',
          'btn-primary',
          'pull-right',
        )
      )
    )) . theme_table($podcast_table);
  }

  public function getSubscribers($node) {    
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->get('podcast/' . $node . '/subscribers');
    $results = $request->send()->json();

    return $results['subscribers'];
  }

  /**
   * Generates a Podcast.
   *
   * @param integer $id
   *   The node ID to built a podcast for.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The podcast feed in XML optimized for iTunes.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the node ID is invalid or not found.
   */
  public function podcast_feed($id) {
    // Validate the widget is valid, and the node ID is an actual node.
    if (!is_numeric($id) || !$node = node_load($id)) {
      throw new NotFoundHttpException();
    }

    global $base_url;
    global $user;

    $author = user_load($node->getValue()['uid'][0]['target_id']);
    $image = ($node->getValue()['field_image'][0]) ? entity_load('file', $node->getValue()['field_image'][0]['target_id']) : '';

    // Build the Podcast information from the Node.
    $podcast = array();

    $podcast['title'] = $node->title->value;
    $podcast['description'] = $node->body->value;
    $podcast['short_description'] = $node->body->summary;
    $podcast['website'] = $base_url . '/' . node_uri($node)['path'];
    $podcast['author'] = $author->user_name->value;
    $podcast['author_email'] = $author->mail->value;
    $podcast['feed'] = $base_url . '/' . node_uri($node)['path'] . '/podcast.xml';
    $podcast['image'] = ($image) ? file_create_url($image->uri->value) : '';
    $podcast['copyright'] = $node->field_copyright->value;

    $vimeo_username = $node->field_vimeo->value;

    if ($node->field_type->value == "Vimeo") {
      $vimeo = new VimeoParser(array(
        'id' => $node->id(),
        'user' => $node->getAuthor()->getValue()['name'][0]['value']
      ));
      $podcast['items'] = $vimeo->variables['videos'];
      $podcast['type'] = 'video';
    }
    else {
      $bethel = new BethelParser(array(
        'id' => $node->id(),
        'user' => $node->getAuthor()->getValue()['name'][0]['value']
      ));
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
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->post('podcast/' . $node->id() . '/hit');
    $request->send();

    // Return a JSON formatted array.
    return new Response($podcast_xml, 200, $headers);
  }
}
