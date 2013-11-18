<?php

/**
 * @file
 * Contains \Drupal\bethel_api\BethelAPIController
 */

namespace Drupal\bethel_api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Json;

class BethelAPIController {
  public function locations() { 
    $locations = db_query('SELECT field_locations_location,field_locations_locationLatitude,field_locations_locationLongitude FROM {user__field_locations}')->fetchAllAssoc('field_locations_location');
    // Return a JSON formatted array.
    return new JsonResponse($locations);
  }
}
