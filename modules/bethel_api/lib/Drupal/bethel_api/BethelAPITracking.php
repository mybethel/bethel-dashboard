<?php

/**
 * @file
 * Contains \Drupal\bethel_api\BethelAPITracking
 */

namespace Drupal\bethel_api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Json;

class BethelAPITracking {

  private $tid;
  private $cid;

  // Read the Google Analytics UUID or create a new one.
  public function __construct($info = null) {
    $this->tid = "UA-45872811-1";
    if (isset($_COOKIE['_ga'])) {
      list($version,$domainDepth, $cid1, $cid2) = split('[\.]', $_COOKIE["_ga"],4);
      $contents = array('version' => $version, 'domainDepth' => $domainDepth, 'cid' => $cid1.'.'.$cid2);
      $this->cid = $contents['cid'];
    }
    else $this->cid = $this->generateUUID();
    if ($info) $this->trackHit($info);
  }
  
  // This function is necessary if no Google Analytics UUID is present.
  private function generateUUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
      // 16 bits for "time_mid"
      mt_rand( 0, 0xffff ),
      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand( 0, 0x0fff ) | 0x4000,
      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand( 0, 0x3fff ) | 0x8000,
      // 48 bits for "node"
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
  }
  
  public function trackHit($info = null) {
    if ($info) {      
      $data = array(
        'v' => 1,
        'tid' => $this->tid,
        'cid' => $this->cid,
        't' => 'pageview',
        'dt' => $info['title'],
        'dp' => $info['slug']
      );
    
      $this->sendHit($data);
    }
  }

  // See https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide
  private function sendHit($data = null) {
    if ($data) {
      // Build the POST request.
      $context  = stream_context_create(array('http' => array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($data),
      )));
      return file_get_contents('https://ssl.google-analytics.com/collect', false, $context);
    }
    return false;
  }
}
