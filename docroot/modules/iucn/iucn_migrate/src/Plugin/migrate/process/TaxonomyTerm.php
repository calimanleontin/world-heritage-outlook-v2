<?php

/**
 * @file
 * Contains \Drupal\migrate\Plugin\migrate\process\TaxonomyTerm.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\EntityLookup;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\migrate\MigrateException;


/**
 * This plugin takes a string and returns a tid
 *
 * @MigrateProcessPlugin(
 *   id = "taxonomy_term"
 * )
 */
class TaxonomyTerm extends EntityLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    if (is_array($value)) {
      $value = current($value);
    }
    $return = parent::transform($value, $migrateExecutable, $row, $destinationProperty);
    return $return;
  }

}
