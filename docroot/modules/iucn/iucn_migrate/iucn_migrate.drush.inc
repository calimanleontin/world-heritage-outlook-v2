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
function iucn_migrate_drush_command() {
  $commands['iucn-delete-content'] = [
    'description' => 'Command for deleting previously imported content from file.',
    'aliases' => ['iucndc'],
  ];

  $commands['iucn-migrate-danger-lists'] = [
    'description' => 'Command for importing danger lists from file.',
    'aliases' => ['iucnmdl'],
  ];

  $commands['iucn-migrate-images'] = [
    'description' => 'Command for importing images from file.',
    'aliases' => ['iucnmi'],
  ];

  $commands['iucn-migrate-sites-multilingual'] = [
    'description' => 'Command for importing multilingual sites.',
    'aliases' => ['iucnmsm'],
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

  $migration_names = [
    'iucn_migrate_sites',
    'iucn_migrate_categories',
    'iucn_migrate_thematics',
    'iucn_migrate_designations',
    'iucn_migrate_criterias',
    'iucn_migrate_countries',
  ];
  foreach($migration_names as $migration_name){
    db_query("TRUNCATE migrate_map_$migration_name");
    db_query("DELETE FROM {config} WHERE name LIKE 'migrate_plus.migration.$migration_name%'");
  }
  drupal_flush_all_caches();
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
          $node_id = reset($node_ids);
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

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_images() {
  drush_print(dt("IUCN migrating images ..."));
  $path = drupal_get_path('module', 'iucn_migrate');
  $sites_file_content = file_get_contents($path.'/'.'source/sites.json');

  $sites = Drupal\Component\Serialization\Json::decode($sites_file_content);

  if($sites){
    foreach($sites as $site){
      if(isset($site['siteId'])
        && !empty($site['siteId'])
        && isset($site['thumbnailUrl'])
        && !empty($site['thumbnailUrl'])
      ){
        $node_ids = \Drupal::entityQuery('node')
          ->condition('field_site_id', $site['siteId'])
          ->condition('type', 'site')
          ->execute();
        if($node_ids) {
          $node_id = reset($node_ids);
          $node = Node::load($node_id);
          if($node){


            $image_title = str_replace([" ","/"],["_",""],$node->getTitle());
            $external_image = file_get_contents($site['thumbnailUrl']);
            $image_object = file_save_data($external_image, 'public://site/' . $image_title . '.jpg', FILE_EXISTS_REPLACE);
            if($image_object){
              $node->set('field_image', ['target_id' => $image_object->id()]);
              $node->save();
              drush_print(dt("Added image(" . $site['thumbnailUrl'] . ") to node: $node_id"));
            }else{
              drush_print(dt("Image could not be saved (" . $image_title . ") to node: $node_id"));
            }
          }
        }
      }
    }
  }
  drush_print(dt("All done."));
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_sites_multilingual() {
  drush_print(dt("IUCN migrating multilingual sites ..."));
  $path = drupal_get_path('module', 'iucn_migrate');
  $sites_file_content = file_get_contents($path.'/'.'source/sites.json');

  $sites = Drupal\Component\Serialization\Json::decode($sites_file_content);

  if($sites){
    foreach($sites as $site){
      if(isset($site['siteId'])
        && !empty($site['siteId'])
      ){
        $node_ids = \Drupal::entityQuery('node')
          ->condition('field_site_id', $site['siteId'])
          ->condition('type', 'site')
          ->execute();
        if($node_ids) {
          $node_id = reset($node_ids);
          $node = Node::load($node_id);
          if($node){

            if (isset($site['nameFr']) && !empty($site['nameFr'])){
              if($node->hasTranslation('fr')){
                $node->getTranslation('fr')->setTitle($site['nameFr'])->save();
                drush_print(dt("Updated [fr] translation for node: $node_id"));
              }else{
                $translation = $node->addTranslation('fr', array('title' => $site['nameFr']));
                $translation->save();
                drush_print(dt("Added [fr] translation to node: $node_id"));
              }
            }else{
              if($node->hasTranslation('fr')){
                $node->removeTranslation('fr');
                drush_print(dt("Removed [fr] translation from node: $node_id"));
              }
            }


            if (isset($site['nameEs']) && !empty($site['nameEs'])){
              if($node->hasTranslation('es')){
                $node->getTranslation('es')->setTitle($site['nameEs'])->save();
                drush_print(dt("Updated [es] translation for node: $node_id"));
              }else{
                $translation = $node->addTranslation('es', array('title' => $site['nameFr']));
                $translation->save();
                drush_print(dt("Added [es] translation to node: $node_id"));
              }
            }else{
              if($node->hasTranslation('es')){
                $node->removeTranslation('es');
                drush_print(dt("Removed [es] translation from node: $node_id"));
              }
            }

            $node->save();
          }
        }
      }
    }
  }
  drush_print(dt("All done."));
}


