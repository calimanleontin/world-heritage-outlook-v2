<?php


namespace Drupal\website_utilities;

use Drupal\Core\Site\Settings;

class DrupalInstance {


  const PRODUCTION = 'production';
  const STAGING = 'staging';
  const DEVELOPMENT = 'development';
  const LOCAL = 'local';


  public static function isProductionInstance() {
    $env = self::getEnvironment();
    return strtolower($env) === self::PRODUCTION || strtolower($env) === 'live';
  }

  public static function isStagingInstance() {
    $env = self::getEnvironment();
    return strtolower($env) === self::STAGING;
  }

  public static function isDevelopmentInstance() {
    $env = self::getEnvironment();
    return strtolower($env) === self::DEVELOPMENT
      || strtolower($env) === 'dev'
      || strtolower($env) === 'devel';
  }

  public static function isLocalInstance() {
    $env = self::getEnvironment();
    return strtolower($env) === self::LOCAL;
  }


  public static function getEnvironment($default = self::LOCAL) {
    $ret = $default;
    if ($value = Settings::get('settings')) {
      $ret = $value;
    }
    return $ret;
  }
}
