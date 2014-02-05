<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\Form\PodcastItemEdit.
 */

namespace Drupal\bethel_podcaster\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Guzzle\Http\Client;

class PodcastItemEdit extends ConfigFormBase {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  private $podcastID;
  private $podcastItemUUID;

  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
    $this->podcastID = arg(1);
    $this->podcastItemUUID = arg(2);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'podcast_item_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->get('podcast/' . $this->podcastItemUUID . '?' . time());
    $podcast_item = $request->send()->json();

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $podcast_item['title'],
    );
    $form['date'] = array(
      '#type' => 'textfield',
      '#title' => t('Date'),
      '#default_value' => $podcast_item['date'],
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $podcast_item['description'],
    );
    $form['duration'] = array(
      '#type' => 'textfield',
      '#title' => t('Duration'),
      '#default_value' => $podcast_item['duration'],
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {    
    $client = new Client('http://api.bethel.io/');
    $request = $client->post('podcast', null, array(
      'mediaId' => $this->podcastItemUUID,
      'payload' => array(
        'title' => $form_state['values']['title'],
        'date' => $form_state['values']['date'],
        'description' => $form_state['values']['description'],
        'duration' => $form_state['values']['duration'],
      ),
    ));
    $data = $request->send();

    drupal_set_message(t('Saved podcast item ' . $form_state['values']['title'] . '. Your changes may take 24 hours to reflect in iTunes.'), 'status');

    $response = new RedirectResponse(url('node/' . $this->podcastID));
    $response->send();
  }

}
