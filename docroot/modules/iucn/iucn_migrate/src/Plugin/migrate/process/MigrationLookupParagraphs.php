<?php

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Configs
 *
 * entity_id: true
 *
 * @MigrateProcessPlugin(
 *   id = "migration_lookup_paragraphs",
 * )
 */
class MigrationLookupParagraphs extends MigrationLookup  {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $return = parent::transform($value, $migrate_executable, $row, $destination_property);
    if (!empty($return)) {
      if (count($return) == 2) {
        return ['target_id' => $return[0], 'target_revision_id' => $return[1]];
      }
      else {
        $paragraph = entity_revision_load('paragraph', $return);
        return ['target_id' => $paragraph->id(), 'target_revision_id' => $return];
      }
    }
    return $return;
  }

}
