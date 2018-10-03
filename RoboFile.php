<?php

use Robo\Robo;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

  protected $drush = './vendor/bin/drush';

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
    $execStack->exec("{$this->drush} sql:drop -y");
    $execStack->exec("{$this->drush} sql:query --file=$sql_dump -y");
    return $execStack->run();
  }

  /**
   * Update the local instance.
   *
   * @return \Robo\Result
   * @throws \Robo\Exception\TaskException
   *
   * @command site:update
   */
  public function siteUpdate() {
    $execStack = $this->taskExecStack()->stopOnFail();
    $execStack->exec("{$this->drush} cr");
    $execStack->exec("{$this->drush} updatedb -y");
    $execStack->exec("{$this->drush} entup -y");
    $execStack->exec("{$this->drush} csim -y");
    return $execStack->run();
  }

  /**
   * Install the local instance.
   *
   * @return bool|null|\Robo\Result
   * @throws \Robo\Exception\TaskException
   *
   * @command site:install
   */
  public function siteInstall() {
    if ($this->sqlSync()->wasSuccessful() && $this->siteUpdate()->wasSuccessful()) {
      $execStack = $this->taskExecStack()->stopOnFail();
      $execStack->exec("{$this->drush} user:password iucn password");
      $execStack->exec("{$this->drush} cset system.logging error_level verbose -y");
      $execStack->exec("{$this->drush} cr");
      return $execStack->run();
    }
    return FALSE;
  }
}
