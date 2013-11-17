<?php

/**
 * @file
 * Contains \Drupal\bethel_location\Plugin\Field\FieldType\BethelLocationItem.
 */

namespace Drupal\bethel_location\Plugin\Field\FieldType;

use Drupal\Core\Field\ConfigFieldItemBase;
use Drupal\field\FieldInterface;
use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Json;

/**
 * Plugin implementation of the 'bethel_location' field type.
 *
 * @FieldType(
 *   id = "bethel_location",
 *   label = @Translation("Location"),
 *   description = @Translation("This field stores a geocoded location in the database."),
 *   default_widget = "bethel_location_default",
 *   default_formatter = "bethel_location_text"
 * )
 */
class BethelLocationItem extends ConfigFieldItemBase {

  /**
   * Definitions of the contained properties.
   *
   * @var array
   */
  static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset(static::$propertyDefinitions)) {
      static::$propertyDefinitions['location'] = array(
        'type' => 'string',
        'label' => t('Location address'),
      );
      static::$propertyDefinitions['locationLatitude'] = array(
        'type' => 'string',
        'label' => t('Location latitude'),
      );
      static::$propertyDefinitions['locationLongitude'] = array(
        'type' => 'string',
        'label' => t('Location longitude'),
      );
    }
    return static::$propertyDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldInterface $field) {
    return array(
      'columns' => array(
        'location' => array(
          'description' => 'Full human-readable location address.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ),
        'locationLatitude' => array(
          'description' => 'Geocoded latitude of location address.',
          'type' => 'float',
          'length' => '10,6',
          'not null' => FALSE,
        ),
        'locationLongitude' => array(
          'description' => 'Geocoded longitude of location address.',
          'type' => 'float',
          'length' => '10,6',
          'not null' => FALSE,
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('location')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $item = $this->getValue();

    // Geocode the location input through Google Maps.
    // @todo: Handle multiple results from geocoding.
    $map = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode(String::checkPlain($this->location)) . '&sensor=false');
    $geocode = Json::decode($map);

    $this->location = $geocode['results'][0]['formatted_address'];

    $this->locationLatitude = $geocode['results'][0]['geometry']['location']['lat'];
    $this->locationLongitude = $geocode['results'][0]['geometry']['location']['lng'];
  }

}
