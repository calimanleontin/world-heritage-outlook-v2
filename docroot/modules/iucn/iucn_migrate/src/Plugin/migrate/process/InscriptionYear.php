<?php

/**
 * @file
 * Contains \Drupal\iucn_migrate\Plugin\migrate\process\InscriptionYear.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "iucn_inscription_year",
 * )
 */
class InscriptionYear extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $value . '-01-01';
  }
}
