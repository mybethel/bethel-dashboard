<?php

/**
 * @file
 * Contains \Drupal\bethel_location/LocationController
 */

namespace Drupal\bethel_location;

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

class LocationController implements ContainerInjectionInterface {

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

  public function location_admin() {
    $var = '';
    drupal_set_title('Locations');

    $libraries = array(
      '#attached' => array(
        'library' => array(
          array('bethel_location', 'location-admin'),
          array('system', 'drupal.ajax'),
        ),
      ),
    );

    drupal_render($libraries);

    return twig_render_template(drupal_get_path('module', 'bethel_location') . '/templates/locations.html.twig', $var);
  }
}
