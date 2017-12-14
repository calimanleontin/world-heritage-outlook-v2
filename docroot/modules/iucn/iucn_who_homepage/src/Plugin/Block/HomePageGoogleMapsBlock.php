<?php

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\iucn_who_core\Plugin\Block\GoogleMapsBaseBlock;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;
use Drupal\iucn_who_core\SiteStatus;
use Drupal\website_utilities\DrupalInstance;


/**
 * @Block(
 *   id = "home_page_map",
 *   admin_label = @Translation("Homepage map"),
 * )
 */
class HomePageGoogleMapsBlock extends GoogleMapsBaseBlock {


  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['iucn'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IUCN specific settings'),
      'empty_selection_placeholder' => [
        '#type' => 'textfield',
        '#title' => $this->t('No site is selected placeholder'),
        '#default_value' => $this->configuration['empty_selection_placeholder'],
      ],
      'block_footer_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Text appearing on the footer'),
        '#default_value' => $this->configuration['block_footer_text'],
      ],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['empty_selection_placeholder'] = $values['iucn']['empty_selection_placeholder'];
    $this->configuration['block_footer_text'] = $values['iucn']['block_footer_text'];
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = parent::build();
    array_unshift($content['#attached']['library'], 'iucn_who_homepage/map');
    $content['#cache'] = [
      'max-age' => 360000,
    ];
    $markers = $this->getMarkers();
    foreach ($markers as $marker) {
      $content['#cache']['tags'][] = 'node:' . $marker['id'];
    }

    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['markers'] = $markers;
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['icons'] = $this->getMarkersIcons();
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['empty_placeholder'] = $this->getSiteSelectionPlaceholder();

    $search_form = \Drupal::formBuilder()->getForm('Drupal\iucn_who_homepage\Form\SiteSearchAutocompleteForm');
    $content['output'] = [
      '#theme' => 'homepage_map_block',
      '#markup_map' => parent::getMapMarkup(),
      '#sites_total_count' => SitesQueryUtil::getPublishedSitesCount(),
      '#conservation_ratings' => SitesQueryUtil::getSiteConservationRatings('weight', TRUE),
      '#empty_selection_placeholder_markup' => $this->getSiteSelectionPlaceholder(),
      '#search_form' => $search_form,
      '#block_footer_text' => $this->getConfigParam( 'block_footer_text', $this->t('The first global assessment of natural World Heritage')),
    ];
    return $content;
  }

  private function getMarkers() {
    $ret = [];
    $sites = SitesQueryUtil::getPublishedSites();
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($sites as $node) {
      $latitude = $node->field_geolocation->lat;
      $longitude = $node->field_geolocation->lon;
      $status_id = $this->getSiteStatus($node);
      // Hide sites without coordinates
      if (empty($latitude) || empty($longitude) || empty($status_id)) {
        \Drupal::logger(__CLASS__)->warning(
          'Hiding site NID: @nid from map due to invalid values (coordinates/status)',
          ['@nid' => $node->id()]
        );
        continue;
      }
      $overall_status_level = SiteStatus::getOverallAssessmentLevel($node);
      $threat_level = SiteStatus::getOverallThreatLevel($node);
      $protection_level = SiteStatus::getOverallProtectionLevel($node);
      $value_level = SiteStatus::getOverallValuesLevel($node);
      $detail = [
        '#theme' => 'homepage_map_site_detail',
        '#title' => $node->title->value,
        '#status' => [
          'label' => $overall_status_level ? $overall_status_level->label() : '-',
          'entity' => $overall_status_level,
          'id' => $status_id,
        ],
        '#country' => [
          'label' => $this->getSiteCountryLabel($node),
        ],
        '#thumbnail' => $this->getSiteThumbnail($node),
        '#inscription' => $this->getSiteInscriptionYear($node),
        '#link' => Url::fromRoute('entity.node.canonical', array('node' => $node->id())),
        '#stat_values' => $value_level ? $value_level->label() : '',
        '#stat_threat' => $threat_level ? $threat_level->label() : '',
        '#stat_protection' => $protection_level ? $protection_level->label() : '',
      ];
      $ret[] = [
        'id' => $node->id(),
        'lat' => $latitude,
        'lng' => $longitude,
        'title' => $node->title->value,
        'status_id' => $status_id,
        'thumbnail' => $this->getSiteThumbnail($node),
        'inscription_year' => $this->getSiteInscriptionYear($node),
        'render' => \Drupal::service('renderer')->render($detail),
      ];
    }
    return $ret;
  }


  private function getSiteCountryLabel($node) {
    $countries = [];
    try {
      if (count($node->field_country) > 0) {
        foreach ($node->field_country as $ob) {
          $countries[] = $ob->entity->name->value;
        }
      }
    } catch (\Exception $e) {
      // @todo log
    }
    return implode(', ', $countries);
  }

  private function getSiteThumbnail($node, $style = 'site_map_detail') {
    $style = ImageStyle::load($style);
    if (!empty($node->field_image->entity)) {
      return $style->buildUrl($node->field_image->entity->getFileUri());
    }
    else {
      $path = '/' . drupal_get_path('module', 'iucn_who_homepage') . '/images/no-image-placeholder.png';
      return Url::fromUserInput($path, ['absolute' => TRUE])->toString();
    }
  }

  private function getSiteInscriptionYear($node) {
    if (!empty($node->field_inscription_year->value)) {
      return date('Y', strtotime($node->field_inscription_year->value));
    }
  }

  private function getSiteStatus($node) {
    $ret = null;
    if ($status = SiteStatus::getOverallAssessmentLevel($node)) {
      $ret = $status->id();
    }
    else {
      if (!DrupalInstance::isProductionInstance()) {
        $array = SitesQueryUtil::getSiteConservationRatings();
        $k = array_rand($array);
        $ret = $array[$k]->id();
      }
    }
    return $ret;
  }

  private function getMarkersIcons() {
    $ret = [];
    $status = SitesQueryUtil::getSiteConservationRatings();
    $path = drupal_get_path('module', 'iucn_who_homepage');
    $absolute_url =  sprintf('/%s/dist/spritesheet.png', $path);
    $json_path = $path . '/dist/sprites.json';

    $sprite_json = file_get_contents($json_path, TRUE);
    $decoded_json = JSON::decode($sprite_json);

    foreach($status as $term) {
      $term_identifier = $term->field_css_identifier->value;
      $term_data = $decoded_json['marker-' . $term_identifier];
      $term_data_active = $decoded_json['marker-' . $term_identifier . '-active'];
      $ret['icon' . $term->id()] = [
        'url' => $absolute_url,
        'origin_x' => $term_data['x'],
        'origin_y' => $term_data['y'],
        'width' => $term_data['width'],
        'height' => $term_data['height'],
      ];
      $ret['icon' . $term->id() . 'Active'] = [
        'url' => $absolute_url,
        'origin_x' => $term_data_active['x'],
        'origin_y' => $term_data_active['y'],
        'width' => $term_data_active['width'],
        'height' => $term_data_active['height'],
      ];
    }
    return $ret;
  }
}
