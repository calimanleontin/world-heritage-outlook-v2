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
    $content['row'] = [
      '#prefix' => '<div class="row"><div class="container-fluid">',
      'col-left' => [
        '#markup' => '<div class="col col-sm-3">COL LEFT</div>'
      ],
      'col-center' => [
        '#prefix' => '<div class="col col-sm-9">',
        'map' => [
          '#type' => 'inline_template',
          '#template' => parent::getMapMarkup(),
        ],
        '#suffix' => '</div>',
      ],
      '#suffix' => '</div></div>',
    ];
    $content['#cache'] = ['max-age' => 0];
    array_unshift($content['#attached']['library'], 'iucn_who_homepage/map');
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['markers'] = $this->getMarkers();
    return $content;
  }


  private function buildLeftColumn() {
    $ret = [
      // @todo add as configuration variable
      'title' => ['#markup' => $this->t('2017 Conservation Outlook rating')],
      'ratings' => [
      ],
    ];
    return $ret;
  }

  private function getMarkers() {

    $ret = [];
    $ret[] = ['lat' => '23', 'lng' => '133', 'title' => 'Simple Marker'];
    return $ret;
  }


}
