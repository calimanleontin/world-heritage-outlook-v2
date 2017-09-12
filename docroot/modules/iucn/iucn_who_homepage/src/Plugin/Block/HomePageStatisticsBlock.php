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

  /**
   * {@inheritdoc}
   */
  public function build() {
    // @todo Line below to disables caching
    $content = ['#cache' => ['max-age' => 0]];
    $statistics = $this->getStatistics();
    $content['output'] = [
      '#theme' => 'homepage_statistics',
      '#statistics' => $statistics,
    ];
    return $content;
  }


  public function getStatistics() {
    $ret = [];
    $statistics = SiteStatus::getSitesStatusStatistics();
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
