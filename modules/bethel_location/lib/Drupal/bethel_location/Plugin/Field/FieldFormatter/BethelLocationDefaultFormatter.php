<?php

/**
 * @file
 * Definition of Drupal\bethel_location\Plugin\Field\FieldFormatter\BethelLocationDefaultFormatter.
 */

namespace Drupal\bethel_location\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'bethel_location_text' formatter.
 *
 * @FieldFormatter(
 *   id = "bethel_location_text",
 *   label = @Translation("Location address"),
 *   field_types = {
 *     "bethel_location"
 *   },
 *   settings = {
 *     "title" = ""
 *   }
 * )
 */
class BethelLocationDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items) {
    $elements = array();
    foreach ($items as $delta => $item) {
      $elements[$delta] = array('#markup' => $item->location);
    }
    return $elements;
  }

}