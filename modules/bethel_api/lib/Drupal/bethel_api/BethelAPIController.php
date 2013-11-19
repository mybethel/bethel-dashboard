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
    $locations = db_query("SELECT l.field_locations_location AS 'address', l.field_locations_locationLatitude AS 'lat', l.field_locations_locationLongitude AS 'long', u.uid FROM {node__field_locations} l LEFT JOIN {users} u ON l.entity_id = u.uid")->fetchAllAssoc('address');
    // Return a JSON formatted array.
    return new JsonResponse($locations);
  }
}
