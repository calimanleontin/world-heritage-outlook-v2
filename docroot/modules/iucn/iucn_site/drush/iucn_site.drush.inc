<?php

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Implements hook_drush_command().
 */
function iucn_site_drush_command() {
  $commands = [];
  $commands['iucn-site-update-geojson'] = array(
    'description' => dt('Update geoJson file for every site.'),
    'arguments' => [],
    'options' => [],
    'aliases' => array('iucn-upgeo'),
  );
  $commands['iucn-resave-sites'] = [
    'description' => 'Command for resaving all sites.',
  ];

  return $commands;
}

/**
 * Resave all sites nodes.
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


/**
 * Update geoJson file for every site.
 */
function drush_iucn_site_update_geojson() {
  $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
  $query->condition('type', 'site');
  $node_ids = $query->execute();
  foreach ($node_ids as $nid) {
    $node = Node::load($nid);
    \Drupal::service('iucn_site.utils')->createGeoJson($node);
    \Drupal::service('iucn_site.utils')->updateGeoJson($node);
  }
}
