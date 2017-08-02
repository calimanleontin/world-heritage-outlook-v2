<?php

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\iucn_who_core\Plugin\Block\GoogleMapsBaseBlock;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;


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
    $content['#attached']['drupalSettings']['GoogleMapsBaseBlock'][self::$instance_count]['icons'] = $this->getMarkersIcons();

    $search_form = \Drupal::formBuilder()->getForm('Drupal\iucn_who_homepage\Form\SiteSearchAutocompleteForm');
    $content['output'] = [
      '#theme' => 'homepage_map_block',
      '#markup_map' => parent::getMapMarkup(),
      '#sites_total_count' => SitesQueryUtil::getPublishedSitesCount(),
      '#conservation_ratings' => SitesQueryUtil::getSiteConservationRatings(),
      '#search_form' => $search_form,
    ];
    return $content;
  }

  private function getMarkers() {
    $ret = [];
    $sites = SitesQueryUtil::getPublishedSites();
    foreach($sites as $node) {
      $latitude = $node->field_coordinate_y->value;
      $longitude = $node->field_coordinate_x->value;
      if (empty($latitude) || empty($longitude)) {
        continue;
      }
      $status_id = $this->getSiteStatus($node);
      $detail = [
        '#theme' => 'homepage_map_site_detail',
        '#title' => $node->title->value,
        '#status' => [
          'label' => 'TODO',
          'id' => $status_id,
        ],
        '#country' => [
          'label' => $this->getSiteCountryLabel($node)
        ],
        '#thumbnail' => $this->getSiteThumbnail($node),
        '#inscription' => $this->getSiteInscriptionYear($node),
        '#link' => Url::fromRoute('entity.node.canonical', array('node' => $node->id())),
        '#stat_values' => 'TODO',
        '#stat_threat' => 'TODO',
        '#stat_protection' => 'TODO',
      ];
      $ret[] = [
        'lat' => $latitude,
        'lng' => $longitude,
        'title' => $node->title->value,
        'status_label' => 'TODO',
        'status_id' => $status_id,
        'thumbnail' => $this->getSiteThumbnail($node),
        'country' => 'Algeria',
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

  private function getSiteThumbnail($node, $style = 'sites_thumbnail') {
    $style = ImageStyle::load($style);
    if (!empty($node->field_image->entity)) {
      return $style->buildUrl($node->field_image->entity->getFileUri());
    }
  }

  private function getSiteInscriptionYear($node) {
    if (!empty($node->field_inscription_year->value)) {
      return date('Y', strtotime($node->field_inscription_year->value));
    }
  }

  private function getSiteStatus($node) {
    // @todo
    $array = SitesQueryUtil::getSiteConservationRatings();
    $k = array_rand($array);
    return $array[$k]->id();
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
    }
    return $ret;
  }
}
