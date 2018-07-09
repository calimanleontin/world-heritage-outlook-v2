<?php

use Robo\Robo;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

  /**
   * Sync DB from production.
   *
   * @command sql:sync
   */
  public function sqlSync() {
    $url =  Robo::config()->get('project.sync.sql.url');
    $username = Robo::config()->get('project.sync.sql.username');
    $password = Robo::config()->get('project.sync.sql.password');
    $tmp_sql_dump = '/tmp/latest.sql';
    $tmp_sql_dump_gz = '/tmp/latest.sql.gz';

    $collection = $this->collectionBuilder();
    $collection->addTask(
      $this->taskExec('rm')->option('-f')->arg($tmp_sql_dump)
    );
    $collection->addTask(
      $this->taskExec('rm')->option('-f')->arg($tmp_sql_dump_gz)
    );
    $collection->addTask(
      $this->taskExec('curl')
      ->arg($url)
      ->option('-o', $tmp_sql_dump_gz)
      ->option('-u', "$username:$password")
    );
    $collection->addTask(
      $this->taskExec('drush')
        ->dir('./docroot')
        ->arg('sql-drop')
        ->arg('-y')
    );
    $collection->addTask(
      $this->taskExec('drush')
      ->dir('./docroot')
      ->arg('sqlq')
      ->arg("--file={$tmp_sql_dump_gz}")
    );
    $collection->run();
  }
}
