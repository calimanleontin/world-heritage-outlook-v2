<?php

/**
 * @file
 * This file contains the block with explore map button.
 */

namespace Drupal\iucn_site\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;


/**
 * @Block(
 *   id = "explore_map",
 *   admin_label = @Translation("Explore Map"),
 * )
 */

class ExploreMapBlock extends BlockBase {

  const CONFIG_KEY_PREFIX = 'iucn_site.explore_map_block.button_prefix';
  const CONFIG_KEY_TITLE = 'iucn_site.explore_map_block.button_title';
  const CONFIG_KEY_URL = 'iucn_site.explore_map_block.button_url';

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button title'),
      '#required' => TRUE,
      '#description' => $this->t('Button title in <b>english</b> ex. Explore map'),
      '#size' => 40,
      '#default_value' => \Drupal::state()->get(self::CONFIG_KEY_TITLE),
    ];

    $form['button_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button prefix'),
      '#description' => $this->t('Text that will be displayed right before the button. Leave empty if you don\'t any text to be displayed. Button prefix in <b>english</b> ex. or'),
      '#size' => 10,
      '#default_value' => \Drupal::state()->get(self::CONFIG_KEY_PREFIX),
    ];

    $form['button_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button url'),
      '#required' => TRUE,
      '#size' => 40,
      '#default_value' => \Drupal::state()->get(self::CONFIG_KEY_URL),
      '#description' => $this->t('You can also enter an internal path such as /node/1 or an external URL such as http://example.com (this will open in a new browser window). Enter <front> to link to the front page.<br/> If using an internal url, this will automatically will redirect to translated url based on Interface text language selected for the current page.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    \Drupal::state()->set(self::CONFIG_KEY_PREFIX, $values['button_prefix']);
    if ($button_title = $values['button_title']) {
      \Drupal::state()->set(self::CONFIG_KEY_TITLE, $button_title);
    }
    if ($button_url = $values['button_url']) {
      \Drupal::state()->set(self::CONFIG_KEY_URL, $button_url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // @todo remove line below to allow caching in production
    $content = ['#cache' => ['max-age' => 0]];

    $config_button_url = \Drupal::state()->get(self::CONFIG_KEY_URL);
    $button_url = '#';
    $button_url_target = '_self';

    try {
      $url = Url::fromRoute($config_button_url);
      $button_url = $url->toString();
    }
    catch (\Exception $e) {
      try {
        $url = Url::fromUserInput($config_button_url);
        $button_url = $url->toString();
      }
      catch (\Exception $e) {
        $button_url = $config_button_url;
        $button_url_target = '_blank';
      }
    }

    $content['output'] = [
      '#theme' => 'explore_map',
      '#button_prefix' => \Drupal::state()->get(self::CONFIG_KEY_PREFIX),
      '#button_title' => \Drupal::state()->get(self::CONFIG_KEY_TITLE),
      '#button_url' => $button_url,
      '#button_url_target' => $button_url_target,
    ];
    return $content;
  }
}