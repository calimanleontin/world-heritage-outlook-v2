<?php

/**
 * @file
 * Another base class for blocks that are rendering maps.
 */


namespace Drupal\iucn_who_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

abstract class GoogleMapsBaseBlock extends BlockBase {


  static $instance_count = 0;


  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['map_width'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Map width'),
      '#description' => $this->t('Map width as CSS declaration (i.e. 100%, 100px etc.)'),
      '#default_value' => $this->getConfigParam('map_width', '100%'),
    ];
    $form['map_height'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Map height'),
      '#description' => $this->t('Map height as CSS declaration (i.e. 100%, 100px etc.)'),
      '#default_value' => $this->getConfigParam( 'map_height', '400px'),
    ];
    $form['map_init_lat'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Map initial latitude'),
      '#description' => $this->t('Map latitude coordinate when displayed'),
      '#default_value' => $this->getConfigParam( 'map_init_lat', '2.8'),
    ];
    $form['map_init_lng'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Map initial lng'),
      '#description' => $this->t('Map longitude coordinate when displayed'),
      '#default_value' => $this->getConfigParam( 'map_init_lng', '-187.3'),
    ];
    $form['map_init_zoom'] = [
      "#type" => 'textfield',
      '#title' => $this->t('Map initial zoom'),
      '#description' => $this->t('Map default zoom'),
      '#default_value' => $this->getConfigParam( 'map_init_zoom', '2'),
    ];
    $form['map_init_type'] = [
      "#type" => 'select',
      '#title' => $this->t('Map type'),
      '#options' => array(
        'roadmap' => 'Normal',
        'satellite' => 'Satellite (photographic map)',
        'hybrid'  => 'Hybrid (photographic map with roads, city names)',
        'terrain' => 'Terrain (map with mountains, rivers, etc.)',
      ),
      '#description' => $this->t('Initial map background'),
      '#default_value' => $this->getConfigParam( 'map_init_type', 'google.maps.MapTypeId.ROADMAP'),
    ];
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['map_width'] = $values['map_width'];
    $this->configuration['map_height'] = $values['map_height'];
    $this->configuration['map_init_lat'] = $values['map_init_lat'];
    $this->configuration['map_init_lng'] = $values['map_init_lng'];
    $this->configuration['map_init_zoom'] = $values['map_init_zoom'];
    $this->configuration['map_init_type'] = $values['map_init_type'];
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    self::$instance_count += 1;
    $content = [];
    $content['#attached']['library'][] = 'google_maps_api/core';
    $content['#attached']['library'][] = 'iucn_who_core/map-base';
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count] = [
      'map_init_lat' => $this->getConfigParam( 'map_init_lat', '2.8'),
      'map_init_lng' => $this->getConfigParam( 'map_init_lng', '-187.3'),
      'map_init_zoom' => $this->getConfigParam( 'map_init_zoom', '2'),
      'map_init_type' => $this->getConfigParam( 'map_init_type', 'google.maps.MapTypeId.ROADMAP'),
    ];
    return $content;
  }


  public function getMapMarkup() {
    return sprintf(
      '<div id="map-%s" data-instance="%s" class="map-container" style="width: %s; height: %s;"></div>',
      self::$instance_count,
      self::$instance_count,
      $this->getConfigParam('map_width', '100%'),
      $this->getConfigParam( 'map_height', '400px')
    );
  }


  /**
   * Wrapper around configuration API to handle missing values.
   *
   * @param string $name
   *    Configuration name
   * @param mixed $default
   *    Default value
   *
   * @return string
   *    Configuration value
   */
  protected function getConfigParam($name, $default = '') {
    $config = $this->getConfiguration();
    return isset($config[$name]) ? $config[$name] : $default;
  }
}
