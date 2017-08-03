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
    $ret = [];
    $terms = SitesQueryUtil::getSiteConservationRatings();
    foreach ($terms as $term) {
      $name = $term->field_css_identifier->value;
      if (!empty($name)) {
        $ret[$name] = $term->name->value;
      }
    }
    return $ret;
  }
}