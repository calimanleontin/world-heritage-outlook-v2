<?php
/**
 * Created by PhpStorm.
 * User: cristiroma
 * Date: 8/3/17
 * Time: 5:58 PM
 */

namespace Drupal\iucn_who_core;


use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorage;

class SiteStatus {

  use StringTranslationTrait;

  const IUCN_OUTLOOK_STATUS_GOOD = 'good';

  const IUCN_OUTLOOK_STATUS_GOOD_CONCERNS = 'good-concerns';

  const IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS = 'significant-concern';

  const IUCN_OUTLOOK_STATUS_CRITICAL = 'critical';

  const IUCN_OUTLOOK_STATUS_DATA_DEFICIENT = 'data-deficient';

  const IUCN_OUTLOOK_STATUS_DATA_COMING_SOON = 'coming-soon';

  public static function all() {
    return [
      self::IUCN_OUTLOOK_STATUS_GOOD,
      self::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS,
      self::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS,
      self::IUCN_OUTLOOK_STATUS_CRITICAL,
      self::IUCN_OUTLOOK_STATUS_DATA_DEFICIENT,
      self::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON,
    ];
  }

  public static function labels() {
    $all = [
      self::IUCN_OUTLOOK_STATUS_GOOD,
      self::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS,
      self::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS,
      self::IUCN_OUTLOOK_STATUS_CRITICAL,
      self::IUCN_OUTLOOK_STATUS_DATA_DEFICIENT,
      self::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON,
    ];
    $ret = [];
    $terms = SitesQueryUtil::getSiteConservationRatings();
    foreach ($terms as $term) {
      $name = $term->field_css_identifier->value;
      if (!empty($name) && in_array($name, $all)) {
        $ret[$name] = $term->name->value;
      }
    }
    return $ret;
  }

  /**
   * @param $identifier
   *
   * @return \Drupal\taxonomy\TermInterface
   */
  public static function getTermStatusByIdentifier($identifier) {
    $ret = null;
    $terms = SitesQueryUtil::getSiteConservationRatings();
    // @todo - Optimize
    foreach ($terms as $term) {
      $name = $term->field_css_identifier->value;
      if (!empty($name) && $name == $identifier) {
        $ret = $term;
      }
    }
    return $ret;
  }


  /**
   * Retrieve the current global assessment level for a site.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\taxonomy\TermInterface
   *   Term containing the site status or NULL for error.
   *
   * @todo Implement a caching strategy to avoid loading all site nodes to
   * compute. We need to find out the proper cache tags (nodes of type
   * site and assessment).
   */
  public static function getOverallAssessmentLevel($node) {
    $ret = NULL;
    /** @var $node object */
    if (empty($node)) {
      return NULL;
    }
    try {
      if (!empty($node->field_current_assessment->entity)) {
        if ($assessment = $node->field_current_assessment->entity) {
          $ret = $assessment->field_as_global_assessment_level->entity;
        }
      }
      else if ($node->isPublished()) {
        $ret = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON);
      }
    } catch (\Exception $e) {
      \Drupal::logger(__CLASS__)->error(
        'Exception while computing site global status for site NID: @nid',
        array('@nid' => $node->id())
      );
    }
    return $ret;
  }


  /**
   * Retrieve the current global threat status for a site.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\taxonomy\TermInterface
   */
  public static function getOverallThreatLevel($node) {
    $ret = NULL;
    /** @var $node object */
    if (empty($node)) {
      return $ret;
    }
    try {
      if ($node->field_current_assessment) {
        if ($term = $node->field_current_assessment->entity) {
          $ret = $term->field_as_threats_rating->entity;
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger(__CLASS__)->error(
        'Exception while computing site threat level for site NID: @nid',
        array('@nid' => $node->id())
      );
    }
    return $ret;
  }


  /**
   * Retrieve the current global protection level for a site.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\taxonomy\TermInterface
   */
  public static function getOverallProtectionLevel($node) {
    $ret = NULL;
    /** @var $node object */
    if (empty($node)) {
      return $ret;
    }
    try {
      if ($node->field_current_assessment) {
        if ($term = $node->field_current_assessment->entity) {
          $ret = $term->field_as_protection_ov_rating->entity;
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger(__CLASS__)->error(
        'Exception while computing site protection level for site NID: @nid',
        array('@nid' => $node->id())
      );
    }
    return $ret;
  }

  /**
   * Retrieve the current global protection level for a site.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\taxonomy\TermInterface
   */
  public static function getOverallValuesLevel($node) {
    $ret = NULL;
    /** @var $node object */
    if (empty($node)) {
      return $ret;
    }
    try {
      if ($node->field_current_assessment) {
        if ($term = $node->field_current_assessment->entity) {
          $ret = $term->field_as_vass_wh_state->entity;
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger(__CLASS__)->error(
        'Exception while computing site values level for site NID: @nid',
        array('@nid' => $node->id())
      );
    }
    return $ret;
  }


  /**
   * Compute the percentages of sites by status.
   *
   * @return array
   *   Array keyed by status and percentage as value
   */
  public static function getSitesStatusStatistics() {
    $ret = [];
    $good = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_GOOD);
    $good_concerns = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS);
    $significant = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS);
    $critical = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_CRITICAL);

    $statuses = [];
    if ($good) { $statuses[$good->id()] = 0; }
    if ($good_concerns) { $statuses[$good_concerns->id()] = 0; }
    if ($significant) { $statuses[$significant->id()] = 0; }
    if ($critical) { $statuses[$critical->id()] = 0; }

    $sites = SitesQueryUtil::getPublishedSites();
    $total = 0;
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($sites as $node) {
      if ($status = SiteStatus::getOverallAssessmentLevel($node)) {
        if (isset($statuses[$status->id()])) {
          $statuses[$status->id()] += 1;
          $total++;
        }
      }
      else {
        // @todo Warning
      }
    }
    foreach($statuses as $status_id => $count) {
      $ret[$status_id] = number_format((100 * $count) / $total ,  2, '.', '');
    }
    return $ret;
  }

  /**
   * Return possible statuses.
   *
   * @return array
   *   Array keyed by status
   */
  public static function getStatistictsTerms(){
    $ret = [];
    if($good = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_GOOD)){
      $ret[] = $good->id();
    }
    if($good_concerns = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS)){
      $ret[] = $good_concerns->id();
    }
    if($significant = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS)){
      $ret[] = $significant->id();
    }
    if($critical = self::getTermStatusByIdentifier(self::IUCN_OUTLOOK_STATUS_CRITICAL)){
      $ret[] = $critical->id();
    }
    return $ret;
  }

  /**
   * Retrieve the taxonomy of assessment_benefits_category as tree containing
   * only the terms in use.
   *
   * @todo test
   * @return array
   *   Two-level array of terms with roots and each root having 'children' property
   */
  public static function getBenefitsCategoriesTreeInUse() {
    $whitelist = SitesQueryUtil::getBenefitsCategoriesInUse();
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('assessment_benefits_category');
    // Add roots
    $parents = $ret = [];
    foreach($terms as $term) {
      if ($term->depth == 0) {
        $parent = Term::load($term->tid);
        $parent->children = [];
        $parents[$term->tid] = $parent;
      }
    }
    // Assign children
    foreach($terms as $term) {
      if ($term->depth == 1 && in_array($term->tid, $whitelist)) {
        // We do not have multi-parent terms
        $parent_id = $term->parents[0];
        if (!empty($parents[$parent_id])) {
          $parent = $parents[$parent_id];
          $parent->children[$term->tid] = Term::load($term->tid);
        }
      }
    }
    // Remove childless parents
    foreach($parents as $parent) {
      if (!empty($parent->children)) {
        $ret[$parent->id()] = $parent;
      }
    }
   /* foreach($ret as $k){
      if($k->children){
        foreach($k->children as $child){
          if($child->field_category_image){
            if(!empty($child->field_category_image) && $child->field_category_image->entity){
              var_dump($child->field_category_image->entity->getFileUri());
            }
          }
        }
      }
    }
    die('EXIT');*/
    return $ret;
  }
}
