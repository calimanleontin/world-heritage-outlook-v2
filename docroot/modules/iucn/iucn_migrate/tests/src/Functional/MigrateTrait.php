<?php

namespace Drupal\Tests\iucn_migrate\Functional;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Entity\Migration;

trait MigrateTrait {

  public function migrate($migrations) {
    /* @var \Drupal\migrate\Plugin\MigrationPluginManager $service */
    $service = \Drupal::service('plugin.manager.migration');

    foreach ($migrations as $id) {
      /* @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $service->createInstance($id);
      if (empty($migration)) {
        throw new \Exception($id . ' migration was not found');
      }

      $migration->getIdMap()->prepareUpdate();
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $result = $executable->import();
      if ($result == MigrationInterface::RESULT_FAILED) {
        throw new \Exception($id . ' migration failed');
      }
    }
  }

}
