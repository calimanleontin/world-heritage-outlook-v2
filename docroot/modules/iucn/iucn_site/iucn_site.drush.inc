<?php

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Component\Utility;


/**
 * Implements hook_drush_command().
 */
function iucn_site_drush_command() {
  $commands['iucn-resave-sites'] = [
    'description' => 'Command for resaving all sites.',
  ];

  return $commands;
}
/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_site_iucn_resave_sites() {
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'type' =>
        [
          'site',
        ],
    ]);
  if ($nodes) {
    foreach ($nodes as $node) {
      $node->save();
    }
  }
  drupal_flush_all_caches();
}
