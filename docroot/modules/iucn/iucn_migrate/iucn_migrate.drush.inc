<?php

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\UrlHelper;
use Drupal\paragraphs\Entity\Paragraph;


/**
 * Implements hook_drush_command().
 */
function iucn_migrate_drush_command() {
  $commands['iucn-delete-content'] = [
    'description' => 'Command for importing content from file.',
    'aliases' => ['iucndc'],
  ];

  $commands['iucn-migrate-danger-lists'] = [
    'description' => 'Command for importing danger lists from file.',
    'aliases' => ['iucnmdl'],
  ];
 return $commands;
}
/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_delete_content() {
  //Remove all sites
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(array('type' => ['site']));
  $index = 0;
  foreach ($nodes as $node) {
    $node->delete();
    drush_print(dt("Deleted node:" . $node->id()));
    $index++;
  }
  drush_print(dt("Deleted $index nodes"));
  drush_print(dt("----------------------------"));

  $taxonomy_terms = [
    'category',
    'country',
    'designation',
    'inscription_criteria',
    'iucn_region',
    'theme',
    'unesco_region',
  ];

  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $taxonomy_terms,'IN')
    ->execute();

  $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $entities = $controller->loadMultiple($tids);
  $controller->delete($entities);
  drush_print(dt("Deleted all " . implode(",", $taxonomy_terms) . " terms."));
  drupal_flush_all_caches();
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_danger_lists() {
  $path = drupal_get_path('module', 'iucn_migrate');
  $sites_file_content = file_get_contents($path.'/'.'source/sites.json');

  $sites = Drupal\Component\Serialization\Json::decode($sites_file_content);
  if($sites){
    foreach($sites as $site){

      if(isset($site['siteId'])
        && !empty($site['siteId'])
        && isset($site['dangerList'])
        && count($site['dangerList']) > 0
      ){
        $node_ids = \Drupal::entityQuery('node')
          ->condition('field_site_id', $site['siteId'])
          ->condition('type', 'site')
          ->execute();
        if($node_ids){
          reset($node_ids);
          $node_id = key($node_ids);
          $node = Node::load($node_id);
          if($node){
            foreach($site['dangerList'] as $danger_list){
              $paragraph = Paragraph::create(['type' => 'danger_list',]);
              $paragraph->set('field_date', $danger_list['year']);
              $paragraph->set('field_status', strtolower($danger_list['dangerListStatus']));
              $paragraph->isNew();
              $paragraph->save();
              $current = $node->get('field_danger_list')->getValue();
              $current[] = array(
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              );
              $node->set('field_danger_list', $current);
              $node->save();

              drush_print(dt("Added danger list item (" . $danger_list['year'] . ", " . $danger_list['dangerListStatus'] . ") to node: $node_id"));
            }
          }
        }
      }
    }
  }
}
