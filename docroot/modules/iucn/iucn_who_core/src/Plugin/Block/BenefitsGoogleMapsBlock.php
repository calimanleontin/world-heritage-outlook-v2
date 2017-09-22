<?php

namespace Drupal\iucn_who_core\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\iucn_who_core\Plugin\Block\GoogleMapsBaseBlock;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;
use Drupal\iucn_who_core\SiteStatus;
use Drupal\website_utilities\DrupalInstance;
use Drupal\file\Entity\File;



/**
 * @Block(
 *   id = "benefits_page_map",
 *   admin_label = @Translation("Benefits page map"),
 * )
 */
class BenefitsGoogleMapsBlock extends GoogleMapsBaseBlock {


  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = parent::build();
    array_unshift($content['#attached']['library'], 'iucn_who_core/benefits-map');

    // @todo remove line below to allow caching in production
    $content['#cache'] = ['max-age' => 0];
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['markers'] = $this->getMarkers();
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['icons'] = $this->getMarkersIcons();

    $content['output'] = [
      '#theme' => 'benefits_map_block',
      '#markup_map' => parent::getMapMarkup(),
      '#categories' => self::getBenefitsTermsTree(),
      '#empty_selection_placeholder_markup' => $this->getSiteSelectionPlaceholder(),
    ];
    return $content;
  }


  private static function getBenefitsTermsTree() {
    return SiteStatus::getBenefitsCategoriesTreeInUse();
  }

  private function getMarkers() {
    $ret = [];
    $sites = SitesQueryUtil::getPublishedSites();
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($sites as $node) {
      $latitude = $node->field_geolocation->lat;
      $longitude = $node->field_geolocation->lon;
      $status_id = $this->getSiteStatus($node);
      $benefits = $this->getSiteBenefits($node);

      // Hide sites without coordinates.
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
      $value_level = SiteStatus::getOverallProtectionLevel($node);


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
        '#stat_values' => $value_level ? $value_level->label() : '-',
        '#stat_threat' => $threat_level ? $threat_level->label() : '-',
        '#stat_protection' => $protection_level ? $protection_level->label() : '-',
      ];

      $ret[] = [
        'id' => $node->id(),
        'lat' => $latitude,
        'lng' => $longitude,
        'title' => $node->title->value,
        'status_id' => $status_id,
        'benefits' => $benefits,
        'thumbnail' => $this->getSiteThumbnail($node),
        'inscription_year' => $this->getSiteInscriptionYear($node),
        'render' => \Drupal::service('renderer')->render($detail),
      ];
    }
    return $ret;
  }

  private function getSiteStatus($node) {
    $ret = NULL;
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


  private function getSiteCountryLabel($node) {
    $countries = [];
    try {
      if (count($node->field_country) > 0) {
        foreach ($node->field_country as $ob) {
          $countries[] = $ob->entity->name->value;
        }
      }
    }
    catch (\Exception $e) {
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

  private function getSiteBenefits($node){
    $categories = [];
    if (!empty($node->field_current_assessment)) {
      $current_assesment = $node->field_current_assessment->entity;
      if (!empty($current_assesment->field_as_benefits)) {
        foreach ($current_assesment->field_as_benefits as $as_benefit) {
          $benefit = $as_benefit->entity;
          if (!empty($benefit->field_as_benefits_category)) {
            foreach ($benefit->field_as_benefits_category as $as_benefits_category) {
              /** @var \Drupal\taxonomy\TermInterface $benefit_category */
              $benefit_category = $as_benefits_category->entity;
              $categories[$benefit_category->id()] = $benefit_category->id();
            }
          }
        }
      }
    }
    return $categories;
  }


  private function setMarkersIcons($category, &$categories) {
    if ($name = $category->getName()) {
      /** @var \Drupal\taxonomy\TermInterface $category */
      if (!empty($category->field_image) && $category->field_image->entity && $category->field_image->entity->getFileUri()) {
        $categories[$category->id()] = [
          'id' => $category->id(),
          'url' => Url::fromUri(file_create_url($category->field_image->entity->getFileUri()), ['absolute' => TRUE])->toString(),
        ];
      }
      else {
        $url = sprintf('/%s/images/marker-%s.png',
          drupal_get_path('module', 'iucn_who_core'),
          'all'
        );
        $categories[$category->id()] = [
          'id' => 'all',
          'url' => Url::fromUserInput($url, ['absolute' => TRUE])->toString(),
        ];
      }
      if (is_array($category->children)) {
        foreach ($category->children as $child) {
          self::setMarkersIcons($child, $categories);
        }
      }
    }
  }

  private function getMarkersIcons() {
    $ret = [];

    $url = sprintf('/%s/images/marker-%s.png',
      drupal_get_path('module', 'iucn_who_core'),
      'all'
    );
    $ret['all'] = [
      'id' => 'all',
      'url' => Url::fromUserInput($url, ['absolute' => TRUE])->toString(),
    ];

    $url = sprintf('/%s/images/marker-%s.png',
      drupal_get_path('module', 'iucn_who_core'),
      'all-active'
    );
    $ret['all_active'] = [
      'id' => 'all_active',
      'url' => Url::fromUserInput($url, ['absolute' => TRUE])->toString(),
    ];

    $categories = self::getBenefitsTermsTree();
    foreach ($categories as $category) {
      /** @var \Drupal\taxonomy\TermInterface $category */
      self::setMarkersIcons($category, $ret);
    }

    return $ret;
  }

}
