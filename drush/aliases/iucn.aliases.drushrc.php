<?php

$aliases['test'] = array(
  'uri' => 'http://iucn-who.edw.ro',
  'db-allows-remote' => TRUE,
  'remote-host' => '5.9.54.24',
  'remote-user' => 'php',
  'root' => '/var/www/html/iucn-who/docroot',
  'path-aliases' => array(
    '%files' => 'sites/default/files',
  ),
);

// Add your local aliases.
if (file_exists(dirname(__FILE__) . '/aliases.local.php')) {
  include dirname(__FILE__) . '/aliases.local.php';
}
