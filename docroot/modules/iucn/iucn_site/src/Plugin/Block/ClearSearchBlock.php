<?php

/**
 * @file
 * This file contains the block with clear search button.
 */

namespace Drupal\iucn_site\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * @Block(
 *   id = "clear_search",
 *   admin_label = @Translation("Clear search"),
 * )
 */

class ClearSearchBlock extends BlockBase {

  const CONFIG_KEY_TITLE = 'iucn_site.clear_search_block.button_title';

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button title'),
      '#required' => TRUE,
      '#description' => $this->t('Button title in <b>english</b> ex. Clear seach'),
      '#size' => 40,
      '#default_value' => \Drupal::state()->get(self::CONFIG_KEY_TITLE),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    if ($button_title = $values['button_title']) {
      \Drupal::state()->set(self::CONFIG_KEY_TITLE, $button_title);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content['output'] = [
      '#theme' => 'clear_search',
      '#button_title' => \Drupal::state()->get(self::CONFIG_KEY_TITLE),
      '#button_url' => Url::fromRoute('view.sites_search.sites_search_page_database')->toString(),
    ];
    return $content;
  }
}