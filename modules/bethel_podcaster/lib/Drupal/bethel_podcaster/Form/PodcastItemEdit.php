<?php

/**
 * @file
 * Contains \Drupal\bethel_podcaster\Form\PodcastItemEdit.
 */

namespace Drupal\bethel_podcaster\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
    $config = \Drupal::config('bethel.podcaster');
    $podcast_item = $config->get('bethel.' . $this->podcastItemUUID);
    
    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $config->get('bethel.' . $this->podcastItemUUID . '.title'),
    );
    $form['date'] = array(
      '#type' => 'textfield',
      '#title' => t('Date'),
      '#default_value' => $config->get('bethel.' . $this->podcastItemUUID . '.date'),
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $config->get('bethel.' . $this->podcastItemUUID . '.description'),
    );
    $form['duration'] = array(
      '#type' => 'textfield',
      '#title' => t('Duration'),
      '#default_value' => $config->get('bethel.' . $this->podcastItemUUID . '.duration'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = \Drupal::config('bethel.podcaster');
  
    $config->set('bethel.' . $this->podcastItemUUID . '.title', $form_state['values']['title'])->save();
    $config->set('bethel.' . $this->podcastItemUUID . '.date', $form_state['values']['date'])->save();
    $config->set('bethel.' . $this->podcastItemUUID . '.description', $form_state['values']['description'])->save();
    $config->set('bethel.' . $this->podcastItemUUID . '.duration', $form_state['values']['duration'])->save();
    
    drupal_set_message(t('Saved podcast item ' . $form_state['values']['title'] . '. Your changes may take 24 hours to reflect in iTunes.'), 'status');
    
    $response = new RedirectResponse(url('node/' . $this->podcastID));
    $response->send();
  }

}
