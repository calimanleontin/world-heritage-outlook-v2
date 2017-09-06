<?php

/**
 * @file
 * Contains \Drupal\migrate\Plugin\migrate\process\Boolean.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Annotation\MigrateProcessPlugin;


/**
 * This plugin takes a string and returns a tid
 *
 * @MigrateProcessPlugin(
 *   id = "cast"
 * )
 */
class Cast extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $cast = $this->configuration['cast_to'];
    switch ($cast) {
      case 'integer':
        return (int) $value;

      case 'boolean':
        return (boolean) $value;

      case 'string':
        return (string) $value;

      default:
        return $value;
    }
  }


  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return FALSE;
  }
}
