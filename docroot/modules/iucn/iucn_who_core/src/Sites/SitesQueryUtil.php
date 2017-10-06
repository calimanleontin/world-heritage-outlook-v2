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


  /**
   * Query all published sites having a valid current assessment set.
   * @return array
   *   Array of NodeInterface objects keyed by entity id.
   */
  public static function getPublishedSitesWithAssessments() {
    $ret = [];
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'site');
    $query->condition('status', 1);
    $nids = $query->execute();
    foreach($nids as $nid) {
      /** @var Node $site */
      $site = Node::load($nid);
      if (!empty($site->field_current_assessment->entity)) {
        $ret[$nid] = $site;
      }
    }
    return $ret;
  }


  public static function getPublishedSitesCount() {
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'site');
    $query->condition('status', 1);
    $query->count();
    return $query->execute();
  }


  public static function getSiteConservationRatings($order_by = 'weight', $get_translation = 'false') {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', 'assessment_conservation_rating');
    if (!empty($order_by)) {
      $query->sort($order_by);
    }
    $tids = $query->execute();
    if (!$get_translation) {
      return Node::load($tids);
    }
    $terms = [];
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach ($tids as $tid) {
      $term = Term::load($tid);
      $term = \Drupal::service('entity.repository')
        ->getTranslationFromContext($term, $langcode);
      $terms[] = $term;
    }
    return $terms;
  }


  public static function searchSiteByName($pattern, $limit = 5) {
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'site')
      ->condition('status', 1)
      ->condition('title', '%'.db_like($pattern).'%', 'LIKE');
    $query->range(0, $limit);
    $nids = $query->execute();
    return Node::loadMultiple($nids);
  }

  /**
   *
   * <pre>
   * SELECT cat.field_as_benefits_category_target_id
   * FROM node_field_data n
   *   INNER JOIN node__field_current_assessment fca ON fca.entity_id = n.nid AND fca.revision_id = n.vid AND fca.bundle = "site"
   *   INNER JOIN node__field_as_benefits fasb ON fca.field_current_assessment_target_id = fasb.entity_id AND fasb.bundle = "site_assessment"
   *   INNER JOIN paragraph__field_as_benefits_category cat ON fasb.field_as_benefits_target_id = cat.entity_id AND cat.deleted = 0
   * WHERE n.status = 1
   * GROUP BY cat.field_as_benefits_category_target_id
   *
   * --  AND cat.revision_id = field_as_benefits_target_revision_id
   *
   * @todo see https://trello.com/c/91HQ9M6A - orphaned paragraphs (cannot join on revision)
   * @todo test
   * @return array
   */
  public static function getBenefitsCategoriesInUse() {
    $query = \Drupal::database()->select('node_field_data', 'n');
    $query->fields('cat', ['field_as_benefits_category_target_id']);
    $query->innerJoin('node__field_current_assessment', 'fca', "fca.entity_id = n.nid AND fca.revision_id = n.vid AND fca.bundle = 'site'");
    $query->innerJoin('node__field_as_benefits', 'fasb', "fca.field_current_assessment_target_id = fasb.entity_id  AND fasb.bundle = 'site_assessment'");
    $query->innerJoin('paragraph__field_as_benefits_category', 'cat', 'fasb.field_as_benefits_target_id = cat.entity_id AND cat.deleted = 0');
    $query->condition('n.status', 1);
    $query->groupBy('cat.field_as_benefits_category_target_id');
    return $query->execute()->fetchCol();
  }
}
