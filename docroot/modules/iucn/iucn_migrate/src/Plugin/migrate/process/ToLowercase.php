<?php

/**
 * @file
 * Contains \Drupal\migrate_source_example\Plugin\migrate\process\FormatDate.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "iucn_to_lowercase",
 * )
 */
class ToLowercase extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return strtolower($value);
	}
}
