<?php

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Component\Utility;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Component\Serialization\Json;

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
  $commands['iucn-update-threat-weight'] = [
    'description' => 'Command for updating assessment_threat taxonomy weights.',
    'aliases' => ['iucnutw'],
  ];
  $commands['iucn-update-benefits-weight'] = [
    'description' => 'Command for updating assessment_benefits_category taxonomy weights.',
    'aliases' => ['iucnubw'],
  ];

  $commands['iucn-update-sites-copyright'] = [
    'description' => 'Command for updating sites description by adding Unesco copyright at the end.',
    'aliases' => ['iucnusc'],
  ];

  $commands['iucn-update-assessments'] = [
    'description' => 'Command for enabling/disabling site assessments.',
    'aliases' => ['iucniua'],
    'options' => array(
      'year' => 'year',
      'action' => 'publish/unpublish',
    ),
  ];

  $commands['iucn-update-threadextentvalue'] = [
    'description' => 'Command for updating threadextentvalue.',
    'aliases' => ['iucnut'],
  ];

  return $commands;
}

function drush_iucn_migrate_iucn_update_threadextentvalue(){

  $assessments_path = \Drupal::config('iucn_migration.settings')->get('assessment_path');
  $assessments_file_content = file_get_contents($assessments_path);
  $assessments = Json::decode($assessments_file_content);
  $assessingThreatsCurrent_count = 0;
  $assessingThreatsPotential_count = 0;

  if ($assessments) {
    foreach ($assessments as $key => $assessment) {
      if (isset($assessment['versions']) && is_array($assessment['versions'])) {
        foreach ($assessment['versions'] as $version) {
          if (isset($version['assessingThreatsCurrent']) && is_array($version['assessingThreatsCurrent'])) {
            foreach ($version['assessingThreatsCurrent'] as $assessingThreats) {
              if (isset($assessingThreats['threadextentvalue']) && $assessingThreats['threadextentvalue']) {
                $sourceid1 = $assessingThreats['currentThreatId'];
                $sourceid3 = 'assessingThreatsCurrent';
                $sourceid4 = 'as_site_threat';
                $value = $assessingThreats['threadextentvalue'];
                $parent = $assessment['assessmentId'];
                $p = iucn_mi_db_identiyfyMigratedParagraph($sourceid1, $sourceid3, $sourceid4, $value, $parent);

                drush_print(dt($p));

                //iucn_mi_identiyfyMigratedParagraph($justification, $specific_threat, $assessment['assessmentCycle'], $assessingThreats['threadextentvalue']);
              }
            }
          }
          /*if (isset($version['assessingThreatsPotential']) && is_array($version['assessingThreatsPotential'])) {
            foreach ($version['assessingThreatsPotential'] as $assessingThreats) {
              if (isset($assessingThreats['threadextentvalue']) && $assessingThreats['threadextentvalue']) {
                $justification = $assessingThreats['justification'];
                $specific_threat = $assessingThreats['potentialThreat'];
                iucn_mi_identiyfyMigratedParagraph($justification, $specific_threat, $assessment['assessmentCycle'], $assessingThreats['threadextentvalue']);
              }
            }
          }*/
        }
      }
    }
  }

  drush_print(dt("----------------------------"));
  drush_print(dt("assessingThreatsCurrent = " . $assessingThreatsCurrent_count));
  drush_print(dt("assessingThreatsPotential = " . $assessingThreatsPotential_count));

}

function iucn_mi_db_identiyfyMigratedParagraph($sourceid1 = NULL, $sourceid3 = NUll, $sourceid4 = NULL, $value = NULL, $parent = NULL){
  /*var_dump(
    [$sourceid1, $sourceid3, $sourceid4,$value, $parent]
  );*/

  // Find the parent.
  $query = \Drupal::database()->select('migrate_map_assessments', 'a');
  $query->fields('a', ['destid1']);
  $query->condition('a.sourceid1', $parent);
  $result = $query->execute()->fetchAll();
  if ($result && isset($result[0])) {
    $node = Node::load($result[0]->destid1);
    if (!$node) {
      return;
    }
  }

  // Find the paragraph.
  $query = \Drupal::database()->select('migrate_map_assessments_paragraphs', 'mp');
  $query->fields('mp', ['destid1']);
  $query->condition('mp.sourceid1', $sourceid1);
  $query->condition('mp.sourceid3', $sourceid3);
  $query->condition('mp.sourceid4', $sourceid4);
  $result = $query->execute()->fetchAll();

  if ($result && isset($result[0])) {
    $paragraph = Paragraph::load($result[0]->destid1);
    if ($paragraph) {
      $paragraph->set('field_as_threats_extent', [assessmentThreatExtentTerm($value)]);
      $paragraph->save();
      $node->save();
      drush_print(dt("Updated node: " .$node->id() . ' paragraph: ' . $paragraph->id() . " with:" . $value));
    }

  }
}


function iucn_mi_identiyfyMigratedParagraph($justification = '', $specific_threat = '', $year = NULL, $value = '', $type = NULL){

  if ($year==2014) {
    return;
  }

  $query = \Drupal::database()->select('paragraph__field_as_description', 'd');
  $query->fields('d', ['entity_id']);
  $query->fields('fd', ['parent_id']);
  $query->join('paragraph__field_as_threats_threat', 't', 't.entity_id = d.entity_id');
  $query->join('paragraphs_item_field_data', 'fd', 'fd.id = d.entity_id');
  $query->condition('d.bundle', 'as_site_threat');
  $query->condition('d.field_as_description_value', $justification, 'like');
  $query->condition('t.field_as_threats_threat_value', $specific_threat, 'like');
  $query->condition('t.bundle', 'as_site_threat');
  $query->condition('fd.parent_type', 'node');
  $query->condition('fd.parent_id', '', '<>');

  $result = $query->execute()->fetchAll();
  $ids = [];

  foreach ($result as $res) {
    $ids[$res->parent_id] = [
      'parent_id' => $res->parent_id,
      'entity_id' => $res->entity_id,
    ];
  }

  foreach ($ids as $id) {
    $node = Node::load($id['parent_id']);
    if ($node) {
      if ($node->get('field_as_cycle')->value == $year) {
        drush_print(dt('assessment [' . $id['parent_id'] . ']: ' . $node->getTitle()));
        $paragraph = Paragraph::load($id['entity_id']);
        /** @var Drupal\paragraphs\Entity\Paragraph $paragraph */
        $paragraph->set('field_as_threats_extent', [assessmentThreatExtentTerm($value)]);
        $paragraph->save();
        $node->save();

        drush_print(dt('paragraph [' . $id['entity_id'] . '] : ' . $paragraph->field_as_threats_threat->value));
        drush_print(dt('Updated Threat extent to : ' . $value));
        drush_print(dt("----------------------------"));
      }
    }
  }
  return TRUE;
}

function assessmentsMigrationDestinationId($sourceid1, $sourceid3){
  $query = \Drupal::database()->select('migrate_map_assessments_paragraphs', 'm');
  $query->fields('m', ['destid1']);
  $query->condition('m.sourceid1', $sourceid1);
  $query->condition('m.$sourceid3', $sourceid3);
  $query->condition('m.sourceid4', 'as_site_threat');
  $result = $query->execute()->fetchCol();
  if (isset($result[0])) {
    return $result[0];
  }
  return FALSE;
}

function assessmentsNodeId($sourceid1){
  $query = \Drupal::database()->select('migrate_map_assessments', 'm');
  $query->fields('m', ['destid1']);
  $query->condition('m.sourceid1', $sourceid1);
  $result = $query->execute()->fetchCol();
  if (isset($result[0])) {
    return $result[0];
  }
  return FALSE;
}

function assessmentsUpdateThreatsExtent($paragraph_id, $value = NULL){
  $paragraph = Paragraph::load($paragraph_id);
  /** @var Drupal\paragraphs\Entity\Paragraph $paragraph */
  $paragraph->set('field_as_threats_extent', assessmentThreatExtentTerm($value));
  if ($paragraph->save()) {
    drush_print(dt('Updated paragraph id: ' . $paragraph_id . ',set field_as_threats_extent to:' . $value));
  }
  else {
    drush_print(dt('COULD NOT SAVE paragraph id: ' . $paragraph_id . ', field_as_threats_extent to:' . $value));
  }


}

/**
 * Checks and returns term id for assessment_threat_extent taxonomy.
 *
 * If the term is not found, it is created.
 */
function assessmentThreatExtentTerm($value) {
  if ($terms = taxonomy_term_load_multiple_by_name($value, 'assessment_threat_extent')) {
    $term = reset($terms);
  }
  else {
    $term = Term::create([
      'name' => $value,
      'vid' => 'assessment_threat_extent',
    ]);
    $term->save();
  }
  return $term->id();
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_update_assessments(){
  $year = drush_get_option('year', FALSE);
  $action = drush_get_option('action', FALSE);

  if(!$year || !action){
    drush_print(dt("Please use this format: iucniua --year=yyyy --action=publish/unpublish "));
    return;
  }
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(array('type' => ['site_assessment'], 'field_as_cycle' => $year));
  if (in_array($action, ['publish', 'unpublish'])) {
    if ($nodes) {
      foreach ($nodes as $node) {
        switch ($action) {
          case 'publish':
            $node->setPublished(TRUE);
            drush_print(dt("PUBLISHED: " . $node->label()));
            break;

          case 'unpublish':
            $node->setPublished(FALSE);
            drush_print(dt("UNPUBLISHED: " . $node->label()));
            break;
        }
        $node->save();
      }
    }
  }
  else {
    drush_print(dt("invalid option for action. Please use --action=publish/unpublish "));
  }
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_update_sites_copyright(){
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(array('type' => ['site']));
  foreach ($nodes as $node) {
    if ($node &&  $description = $node->get('body')->getValue()) {
      $node->body->value = $description[0]['value'] . '
      © UNESCO';
      $node->save();
      drush_print(dt("Update site description: " . $node->id()));
    }
  }

  drush_print(dt("----------------------------"));
  drush_print(dt("All ok."));
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_update_benefits_weight(){
  $weights = [
    'Environmental services',
      'Carbon sequestration',
      'Soil stabilisation',
      'Coastal protection',
      'Flood prevention',
      'Water provision (importance for water quantity and quality)',
      'Pollination',
    'Food',
      'Legal subsistence hunting of wild game',
      'Collection of wild plants and mushrooms',
      'Fishing areas and conservation of fish stocks',
      'Traditional agriculture',
      'Livestock grazing areas',
    'Water',
      'Access to drinking water',
      'Commercial wells',
    'Cultural and spiritual values',
      'Cultural identity and sense of belonging',
      'History and tradition', 	
      'Sacred or symbolic plants or animals', 	
      'Sacred natural sites or landscapes', 	
      'Sacred plants or animals', 	
      'Sense of belonging', 	
      'Wilderness and iconic features',
    'Health and recreation',
      'Collection of resources for the pharmaceuticals industry',
      'Collection of medicinal resources for local use',
      'Outdoor recreation and tourism',
      'Natural beauty and scenery',
    'Knowledge',
      'Importance for research',
      'Contribution to education',
      'Collection of genetic material',
    'Materials',
      'Collection of timber, e.g. fuelwood',
      'Sustainable extraction of materials (e.g. coral, shells, resin, rubber, grass, rattan, etc)',
    'Contribution to local economy',
      'Direct employment',
      'Tourism-related income',
      'Provision of jobs',
    'Nature conservation values',
      'Does management of the site provide jobs (e.g. for managers or rangers)?',
      'Is the protected area valued for its nature conservation?',
    ];
  iucn_update_taxonomy_weights('Assessment benefits category', $weights);
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_update_threat_weight(){

  $weights = [
    'Housing/ Urban Areas',
    'Commercial/ Industrial Areas',
    'Tourism/ Recreation Areas',
    'Crops',
    'Forestry/ Wood production',
    'Livestock Farming / Grazing',
    'Crop production',
    'Marine/ Freshwater Aquaculture',
    'Oil/ Gas exploration/development',
    'Mining/ Quarrying',
    'Renewable Energy',
    'Roads/ Railroads',
    'Utility / Service Lines',
    'Shipping Lanes',
    'Flight Paths',
    'Hunting (commercial/subsistence)',
    'Poaching',
    'Logging/ Wood Harvesting',
    'Fishing / Harvesting Aquatic Resources',
    'Other Biological Resource Use',
    'Tourism/ visitors/ recreation',
    'War, Civil Unrest/ Military Exercises',
    'Other Activities',
    'Fire/ Fire Suppression',
    'Dams/ Water Management or Use',
    'Other Ecosystem Modifications',
    'Invasive Non-Native/ Alien Species',
    'Hyper-Abundant Species',
    'Modified Genetic Material',
    'Water Pollution',
    'Household Sewage/ Urban Waste Water',
    'Industrial/ Military Effluents',
    'Agricultural/ Forestry Effluents',
    'Solid Waste',
    'Air Pollution',
    'Volcanoes',
    'Earthquakes/ Tsunamis',
    'Avalanches/ Landslides',
    'Erosion and Siltation/ Deposition',
    'Habitat Shifting/ Alteration',
    'Droughts',
    'Desertification',
    'Ocean acidification',
    'Temperature extremes',
    'Storms/Flooding',
    'Changes in traditional ways of life and knowledge systems',
    'Identity/ Social Cohesion/ Changes in local population and community',
    'Other',
  ];

  iucn_update_taxonomy_weights('Assessment threat categories', $weights);
}

function iucn_update_taxonomy_weights($taxonomy_label, $weights){
  $count_weights = count($weights);
  $not_found_items = [];

  $vids = Vocabulary::loadMultiple();
  foreach ($vids as $vid) {
    if ($vid->label() == $taxonomy_label) {
      $container = \Drupal::getContainer();
      $terms = $container->get('entity.manager')->getStorage('taxonomy_term')->loadTree($vid->id());
      if (!empty($terms)) {
        $found = 0;
        foreach ($terms as $term) {
          if (in_array($term->name, $weights)) {
            $found++;
            $key = array_search($term->name, $weights);
            drush_print(dt("----------------------------"));
            drush_print(dt('Found ' . $term->name . 'updating the weight from ' . $term->weight . ' to ---> ' . $key));
            /** @var \Drupal\taxonomy\Entity\Term $term */
            drush_print(dt('Done.'));

            $update_term = \Drupal\taxonomy\Entity\Term::load($term->tid);
            if ($update_term) {
              $update_term->weight = $key;
              $update_term->save();
            }
            unset($weights[$key]);
          }
          else {
            $not_found_items[] = $term->name;
          }
        }
      }
      drush_print(dt("----------------------------"));
      drush_print(dt('New weights items:' . $count_weights));
      drush_print(dt('Found and updates items:' . $found));

      if ($not_found_items) {
        drush_print(dt("----------------------------"));
        drush_print(dt('Not Found items:'));
        drush_print(dt("----------------------------"));
        foreach ($not_found_items as $item) {
          drush_print(dt($item));
        }
      }

      if ($weights) {
        drush_print(dt("----------------------------"));
        drush_print(dt('Not Found items but must update:'));
        drush_print(dt("----------------------------"));
        foreach ($weights as $item) {
          drush_print(dt($item));
        }
      }
      break;
    }
  }
  drush_print(dt("----------------------------"));
  drush_print(dt("Finished."));
  drush_print(dt("----------------------------"));
}

/**
 * Drush command logic.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_iucn_migrate_iucn_delete_content() {
  // Remove all sites.
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
    ->condition('vid', $taxonomy_terms, 'IN')
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
            $current = [];
            foreach($site['dangerList'] as $danger_list){
              $paragraph = Paragraph::create(['type' => 'danger_list',]);
              $paragraph->set('field_date', $danger_list['year']);
              $paragraph->set('field_status', strtolower($danger_list['dangerListStatus']));
              $paragraph->isNew();
              $paragraph->save();
              //$current = $node->get('field_danger_list')->getValue();
              $current[] = array(
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              );
              drush_print(dt("Added danger list item (" . $danger_list['year'] . ", " . $danger_list['dangerListStatus'] . ") to node: $node_id"));
            }
            $node->set('field_danger_list', $current);
            $node->save();
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

  if ($sites) {
    foreach ($sites as $site) {
      if (isset($site['siteId'])
        && !empty($site['siteId'])
        && isset($site['thumbnailUrl'])
        && !empty($site['thumbnailUrl'])
      ) {
        $node_ids = \Drupal::entityQuery('node')
          ->condition('field_site_id', $site['siteId'])
          ->condition('type', 'site')
          ->execute();
        if ($node_ids) {
          $node_id = reset($node_ids);
          $node = Node::load($node_id);
          if ($node) {
            $image_title = str_replace([" ","/"], ["_",""], $node->getTitle());
            $external_image = file_get_contents($site['thumbnailUrl']);
            $image_object = file_save_data($external_image, 'public://site/' . $image_title . '.jpg', FILE_EXISTS_REPLACE);
            if ($image_object) {
              $node->set('field_image', ['target_id' => $image_object->id()]);
              $node->save();
              drush_print(dt("Added image(" . $site['thumbnailUrl'] . ") to node: $node_id"));
            }
            else {
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


          /*if (isset($site['nameEs']) && !empty($site['nameEs'])){
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
            }*/

            $node->save();
          }
        }
      }
    }
  }
  drush_print(dt("All done."));
}


