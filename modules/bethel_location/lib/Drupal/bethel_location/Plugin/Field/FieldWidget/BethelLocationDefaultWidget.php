<?php

/**
 * @file
 * Definition of Drupal\bethel_location\Plugin\Field\FieldWidget\BethelLocationDefaultWidget.
 */

namespace Drupal\bethel_location\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;

/**
 * Plugin implementation of the 'bethel_location_default' widget.
 *
 * @FieldWidget(
 *   id = "bethel_location_default",
 *   label = @Translation("Location"),
 *   field_types = {
 *     "bethel_location"
 *   },
 *   settings = {
 *     "placeholder" = ""
 *   }
 * )
 */
class BethelLocationDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    $element['location'] = array(
      '#type' => 'textfield',
      '#title' => t('Location Address'),
      '#default_value' => isset($items[$delta]->location) ? $items[$delta]->location : NULL,
      '#maxlength' => 255,
    );

    return $element;
  }


}
