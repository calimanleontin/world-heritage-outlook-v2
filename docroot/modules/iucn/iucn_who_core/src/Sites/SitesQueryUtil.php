<?php

/**
 * @file
 * Utilities to manipulate site entities.
 */

namespace Drupal\iucn_who_core\Sites;


use Drupal\Core\Entity\Entity;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;


class SitesQueryUtil {


  /**
   * Query all published sites.
   *
   * @return array
   *   Array with NodeInterface objects
   */
  public static function getPublishedSites($order_by = 'title') {
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'site');
    $query->condition('status', 1);
    if (!empty($order_by)) {
      $query->sort($order_by);
    }
    $nids = $query->execute();
    return Node::loadMultiple($nids);
  }

  public static function getPublishedSitesCount() {
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'site');
    $query->condition('status', 1);
    $query->count();
    return $query->execute();
  }


  public static function getSiteConservationRatings($order_by = 'weight') {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', TAXONOMY_SITE_CONSERVATION_RATING);
    if (!empty($order_by)) {
      $query->sort($order_by);
    }
    $tids = $query->execute();
    return Term::loadMultiple($tids);
  }

}
