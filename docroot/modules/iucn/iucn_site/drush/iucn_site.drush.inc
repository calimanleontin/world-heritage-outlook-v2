<?php

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Implements hook_drush_command().
 */
function iucn_site_drush_command() {
  $items = [];
  $items['iucn-site-update-geojson'] = array(
    'description' => dt('Update geoJson file for every site.'),
    'arguments' => [],
    'options' => [],
    'aliases' => array('iucn-upgeo'),
  );
  return $items;
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
