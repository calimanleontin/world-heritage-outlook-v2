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
    $config = Robo::config();
    $url =  $config->get('project.sync.sql.url');
    $username = $config->get('project.sync.sql.username');
    $password = $config->get('project.sync.sql.password');
    $sql_dump = '/tmp/db.sql';
    $sql_dump_gz = '/tmp/db.sql.gz';

    $execStack = $this->taskExecStack()->stopOnFail();
    $execStack->exec("rm -f $sql_dump $sql_dump_gz");
    $execStack->exec("curl $url --create-dirs -o $sql_dump_gz -u $username:$password");
    $execStack->exec("gzip -d $sql_dump_gz");
    $execStack->exec("./vendor/bin/drush sql:drop -y");
    $execStack->exec("./vendor/bin/drush sql:query --file=$sql_dump -y");
    return $execStack->run();
  }
}
