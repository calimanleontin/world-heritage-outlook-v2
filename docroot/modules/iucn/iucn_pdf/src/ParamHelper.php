<?php

namespace Drupal\iucn_pdf;

/**
 * Helper class to allow override url params in cron.
 */
class ParamHelper implements ParamHelperInterface{

  /**
   * Request values.
   * @var string[]
   */
  protected static $values = array();

  /**
   * Override default request value for cron.
   */
  public function overrideValue($key, $value) {
    ParamHelper::$values[$key] = $value;
  }

  /**
   * Get request value.
   */
  public function get($key, $default = null) {
    if (isset(ParamHelper::$values[$key])) {
      return ParamHelper::$values[$key];
    }
    /** @var \Symfony\Component\HttpFoundation\Request $request */
    $request = \Drupal::request();
    return $request->get($key, $default);
  }

}
