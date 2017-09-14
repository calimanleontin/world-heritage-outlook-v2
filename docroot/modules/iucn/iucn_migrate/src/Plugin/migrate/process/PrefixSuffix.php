<?php

/**
 * @file
 * Contains \Drupal\iucn_migrate\Plugin\migrate\process\FormatDate.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Adds strings to a value.
 *
 * plugin: iucn_prefix_suffix
 *  prefix: '('
 *  suffix: ')'
 *
 * @MigrateProcessPlugin(
 *   id = "iucn_prefix_suffix",
 * )
 */
class PrefixSuffix extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }
    if (!empty($this->configuration['prefix'])) {
      if (!preg_match('/^\\' . $this->configuration['prefix'] . '/s', $value)) {
        $value = $this->configuration['prefix'] . $value;
      }
    }
    if (!empty($this->configuration['suffix'])) {
      if (!preg_match('/\\' . $this->configuration['suffix'] . '$/s', $value)) {
        $value .= $this->configuration['suffix'];
      }
    }
    return $value;
  }
}
