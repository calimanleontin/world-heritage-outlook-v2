<?php
$databases['default']['default'] = array (
  'database' => 'drupal',
  'username' => 'root',
  'password' => 'root',
  'prefix' => '',
  'host' => 'db',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);
$settings['hash_salt'] = 'super-secret-hash-salt';
$settings['install_profile'] = 'minimal';
$config_directories['sync'] = realpath(DRUPAL_ROOT . '/../config/default');
$settings['trusted_host_patterns'] = [
  'iucn.local',
];
$settings['file_private_path'] = realpath(DRUPAL_ROOT . '/../private-storage');
/** The encryption key must be a base 64 encoded 256 bit (32 byte) value. */
$settings['encryption_key'] = 'dmVyeS1zZWNyZXQta2V5LXRoYXQtYmFzZTY0LWVuYwo=';
