<?php

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_who_core\Plugin\Block\GoogleMapsBaseBlock;


/**
 * @Block(
 *   id = "home_page_map",
 *   admin_label = @Translation("Homepage map"),
 * )
 */
class HomePageGoogleMapsBlock extends GoogleMapsBaseBlock {


  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = parent::build();
    array_unshift($content['#attached']['library'], 'iucn_who_homepage/map');
    $content['#cache'] = ['max-age' => 0];
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['markers'] = $this->getMarkers();
    $content['output'] = [
      '#theme' => 'homepage_map_block',
      '#markup_map' => parent::getMapMarkup(),
    ];
    return $content;
  }

  private function getMarkers() {

    $ret = [];
    $ret[] = ['lat' => '23', 'lng' => '133', 'title' => 'Simple Marker'];
    return $ret;
  }


}
