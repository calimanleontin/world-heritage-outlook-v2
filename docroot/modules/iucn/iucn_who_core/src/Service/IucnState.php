<?php

namespace Drupal\iucn_who_core\Service;

use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;

class IucnState extends State {

  /**
   * Gets value from settings if exists, or else from state
   *
   * @param $key
   * @param null $default
   *
   * @return mixed|null
   */
  public function get($key, $default = NULL) {
    $settings = Settings::getAll();
    if (array_key_exists($key, $settings)) {
      return $settings[$key];
    }

    return parent::get($key, $default);
  }

}
