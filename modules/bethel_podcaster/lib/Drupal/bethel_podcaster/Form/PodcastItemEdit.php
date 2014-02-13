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
    $connector_url = node_load($this->podcastID)->field_vimeo->getValue()[0]['value'];
    $api_client = new Client('http://api.bethel.io');
    $request = $api_client->get('podcast/' . $this->podcastItemUUID . '?' . time());
    $podcast_item = $request->send()->json();
    
    $filename = explode('/', $podcast_item['url']);
    $filename = $filename[sizeof($filename) - 1];

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => isset($podcast_item['title']) ? $podcast_item['title'] : str_replace('.mp3', '', $filename),
      '#attributes' => array('class' => array('form-control')),
    );
    $form['date'] = array(
      '#type' => 'textfield',
      '#title' => t('Date'),
      '#default_value' => $podcast_item['date'],
      '#attributes' => array('class' => array('form-control')),
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => isset($podcast_item['description']) ? $podcast_item['description'] : '',
      '#attributes' => array('class' => array('form-control')),
    );
    $form['reference'] = array(
      '#type' => 'textfield',
      '#title' => t('Reference'),
      '#default_value' => isset($podcast_item['reference']) ? $podcast_item['reference']['value'] : '',
      '#attributes' => array('class' => array('form-control'), 'autocomplete' => 'off'),
    );
    
    $form['#attached'] = array(
      'library' => array(array('bethel_podcaster', 'podcaster.edit')),
      'js' => array('jQuery(document).ready(function($){$("#edit-reference").typeahead({source:function(query,process){return $.get("' . $connector_url . '/bethel/podcaster/autocomplete/" + query,function(data){return process(data.results);});}});});' => array('type' => 'inline')),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $reference_value = $form_state['values']['reference'];
    $reference_idpos = strpos($form_state['values']['reference'], '[id:')+4;

    $client = new Client('http://api.bethel.io/');
    $request = $client->post('podcast', null, array(
      'mediaId' => $this->podcastItemUUID,
      'payload' => array(
        'title' => $form_state['values']['title'],
        'date' => $form_state['values']['date'],
        'description' => $form_state['values']['description'],
        'reference' => array(
          'value' => $form_state['values']['reference'],
          'id' => substr($reference_value, $reference_idpos, strlen($reference_value)-$reference_idpos-1),
        )
      ),
    ));
    $data = $request->send();

    drupal_set_message(t('Saved podcast item ' . $form_state['values']['title'] . '. Your changes may take 24 hours to reflect in iTunes.'), 'status');

    $response = new RedirectResponse(url('node/' . $this->podcastID));
    $response->send();
  }

}
