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
      // Hide sites without coordinates
      if (empty($latitude) || empty($longitude) || empty($status_id)) {
        \Drupal::logger(__CLASS__)->warning(
          'Hiding site NID: @nid from map due to invalid values (coordinates/status)',
          ['@nid' => $node->id()]
        );
        continue;
      }

      $detail = [
        '#theme' => 'homepage_map_site_detail',
        '#title' => $node->title->value,
        '#country' => [
          'label' => $this->getSiteCountryLabel($node),
        ],
        '#inscription' => $this->getSiteInscriptionYear($node),
        '#link' => Url::fromRoute('entity.node.canonical', array('node' => $node->id())),
      ];
      $ret[] = [
        'id' => $node->id(),
        'lat' => $latitude,
        'lng' => $longitude,
        'title' => $node->title->value,
        'status_label' => 'TODO',
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


  private function getMarkersIcons() {
    $ret = [];
    $status = SitesQueryUtil::getSiteConservationRatings();
    foreach($status as $term) {
      $url = sprintf('/%s/images/marker-%s.png',
        drupal_get_path('module', 'iucn_who_homepage'),
        $term->field_css_identifier->value
      );
      $ret['icon' . $term->id()] = [
        'url' => Url::fromUserInput($url, ['absolute' => true])->toString(),
      ];
      $url = sprintf('/%s/images/marker-%s-active.png',
        drupal_get_path('module', 'iucn_who_homepage'),
        $term->field_css_identifier->value
      );
      $ret['icon' . $term->id() . 'Active'] = [
        'url' => Url::fromUserInput($url, ['absolute' => true])->toString(),
      ];
    }
    return $ret;
  }
}
