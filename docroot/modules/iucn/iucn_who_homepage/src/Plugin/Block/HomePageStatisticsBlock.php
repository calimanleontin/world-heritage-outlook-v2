<?php

/**
 * @file
 * This file contains the block with statistical data shown on the home page.
 */

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_who_core\SiteStatus;

/**
 * @Block(
 *   id = "home_page_statistics",
 *   admin_label = @Translation("Global outlook statistics"),
 * )
 */
class HomePageStatisticsBlock extends BlockBase {

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    foreach(SiteStatus::labels() as $key => $label) {
      $value = isset($config[$key]) ? $config[$key] : '';
      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('@status value (%)', ['@status' => $label]),
        '#size' => 15,
        '#description' => $this->t('Enter value in percent, leave empty to hide in resulting graph.'),
        '#default_value' => $value,
      ];
    }
    return $form;
  }


  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    foreach(SiteStatus::labels() as $key => $label) {
      $this->configuration[$key] = $values[$key];
    }
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    // @todo remove line below to allow caching in production
    $content = ['#cache' => ['max-age' => 0]];
    dpm($this->getStatistics());
    $content['output'] = [
      '#theme' => 'homepage_statistics',
      '#statistics' => $this->getStatistics(),
    ];
    return $content;
  }


  public function getStatistics() {
    $ret = [];
    $config = $this->getConfiguration();
    foreach(SiteStatus::labels() as $key => $label) {
      if (!empty($config[$key])) {
        $ret[$key] = [
          'id' => $key,
          'value' => $config[$key],
          'label' => $label,
        ];
      }
    }
    return $ret;
  }

}