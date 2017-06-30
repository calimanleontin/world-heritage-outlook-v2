<?php
$aliases['test'] = array(
  'uri' => 'http://iucn-who.edw.ro',
  'root' => '/var/www/html/iucn-who/docroot',
  'remote-host' => '5.9.54.24',
  'remote-user' => 'TODO',
);

// Add your local aliases.
if (file_exists(dirname(__FILE__) . '/aliases.local.php')) {
  include dirname(__FILE__) . '/aliases.local.php';
}
