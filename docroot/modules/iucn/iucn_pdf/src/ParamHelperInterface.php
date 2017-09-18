<?php

namespace Drupal\iucn_pdf;

/**
 * Interface for the Print builder service.
 */
interface ParamHelperInterface {

  /**
   * Render any content entity as a Print.
   * The key.
   * @param string $key
   * The default value if the parameter key does not exist
   * @param mixed  $default
   */
  public function overrideValue($key, $value);

  /**
   * Gets a "parameter" value.
   *
   *
   * Order of precedence: GET, PATH, POST
   *
   * Avoid using this method in controllers:
   *
   *  * slow
   *  * prefer to get from a "named" source
   *
   * It is better to explicitly get request parameters from the appropriate
   * public property instead (query, attributes, request).
   *
   * Note: Finding deep items is deprecated since version 2.8, to be removed in 3.0.
   *
   * @param string $key     the key
   * @param mixed  $default the default value if the parameter key does not exist
   *
   * @return mixed
   */
  public function get($key, $default = null);

}
