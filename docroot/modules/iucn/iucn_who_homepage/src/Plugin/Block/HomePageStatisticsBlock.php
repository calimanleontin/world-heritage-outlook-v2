<?php

/**
 * @file
 * This file contains the block with statistical data shown on the home page.
 */

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_who_core\SiteStatus;
use Drupal\taxonomy\Entity\Term;
use Drupal\website_utilities\DrupalInstance;

/**
 * @Block(
 *   id = "home_page_statistics",
 *   admin_label = @Translation("Global outlook statistics"),
 * )
 */
class HomePageStatisticsBlock extends BlockBase {

  public function blockForm($form, FormStateInterface $form_state) {

    $form['iucn_who_homepage.homepage_statistics_block'] = [
      '#markup' => 'You can set custom values for each rating to be shown on Homepage statics block.<br/>Below each rating input is displayed the real calculated value based on current data.<br/>Note, calculated values are shown with precision of 2 digits after the point.<br/>You must round up the values that will be displayed on front-end.<br/>Make sure that the sum is exactly 100.',
    ];

    $statistics = SiteStatus::getSitesStatusStatistics();
    foreach($statistics as $tid => $percentage) {

      $t = \Drupal::state()->get('iucn_who_homepage.homepage_statistics_block.' . $tid);

      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = Term::load($tid);
      $form['iucn_who_homepage.homepage_statistics_block'][$tid] = [
        '#type' => 'textfield',
        '#title' => $term->label(),
        '#required' => TRUE,
        '#description' => $this->t('Calculated value: <b>%percentage %</b>' , ['%percentage' => $percentage]),
        '#size' => 3,
        '#default_value' => $t,
      ];

    }
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    if ($homepage_statistics_values = $values['iucn_who_homepage.homepage_statistics_block']) {
     foreach($homepage_statistics_values as $key => $value){
        \Drupal::state()->set('iucn_who_homepage.homepage_statistics_block.' . $key , $value);
      }
    }
  }

  public function blockValidate($form, FormStateInterface $form_state) {
    if ($statistics = $form_state->getValue('iucn_who_homepage.homepage_statistics_block')) {
      $final_value = 0;
      foreach($statistics as $statistic){
        $final_value += $statistic;
      }
      if ($final_value != 100) {
        $form_state->setErrorByName('iucn_who_homepage.homepage_statistics_block', $this->t('The sum of all statuses must be exaclty 100 (currently the sum is %final_value )', ['%final_value' => $final_value]));
      }
    }
    return $form_state;
  }



  /**
   * {@inheritdoc}
   */
  public function build() {
    $statistics = $this->getStatistics();
    $content = ['output' => [
      '#theme' => 'homepage_statistics',
      '#statistics' => $statistics,
      ],
    ];
    return $content;
  }


  public function getStatistics() {
    $ret = [];
    $terms = SiteStatus::getStatistictsTerms();
    $statistics = [];
    foreach($terms as $t) {
      $statistics[$t] = \Drupal::state()->get('iucn_who_homepage.homepage_statistics_block.' . $t);
    }
    foreach($statistics as $tid => $percentage) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = Term::load($tid);
      $id = $term->field_css_identifier->value;
      $ret[$id] = [
        'id' => $id,
        'value' => $percentage,
        'label' => $term->label(),
      ];
    }
    return $ret;
  }
}
