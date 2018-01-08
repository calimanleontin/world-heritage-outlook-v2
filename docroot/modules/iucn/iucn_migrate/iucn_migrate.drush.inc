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
    'options' => array(
      'start' => 'start',
      'update' => 'update',
    ),
  ];


  $commands['iucn-update-protectiontopic'] = [
    'description' => 'Command for updating assessment_protection_topic.',
    'aliases' => ['iucnupt'],
    'options' => array(
      'start' => 'start',
      'update' => 'update',
    ),
  ];

  $commands['iucn-fix-protectiontopic'] = [
    'description' => 'Command for updating assessment_protection_topic.',
    'aliases' => ['iucnfpt'],
    'options' => array(
      'update' => 'update',
    ),
  ];

  return $commands;
}

/**
 * After running the command make sure to manually update the detected paragraphs:
 * Then remove Assessment protection topic: Assessment of the effectiveness of protection and management in addressing threats outside the site.
 */
function drush_iucn_migrate_iucn_fix_protectiontopic($update = FALSE){

  $site_assessments = \Drupal::entityQuery('node')
    ->condition('type', 'site_assessment')
    ->execute();

  if ($site_assessments) {
    foreach ($site_assessments as $site_assessment_id) {
      $site_assessment = Node::load($site_assessment_id);
      if ($site_assessment) {
        if ($site_protections = $site_assessment->get('field_as_protection')->getValue()) {
          foreach ($site_protections as $site_protection) {
            $paragraph = Paragraph::load($site_protection['target_id']);
            if ($paragraph && $paragraph->get('field_as_protection_topic') && $paragraph->get('field_as_protection_topic')->entity) {
              $label = $paragraph->get('field_as_protection_topic')->entity->label();
              if ($label == 'Assessment of the effectiveness of protection and management in addressing threats outside the site') {
                drush_print(dt($label));
                drush_print(dt($site_assessment->id() . ' : ' . $site_assessment->label()));
                drush_print(dt('-----------------'));
              }
            }
          }
        }
      }
    }
  }

}
/**
* Before running the command make sure to manually update the following taxonomies trems for Assessment protection topic:
* Relationships with local people (including stakeholder relationships, participatory management, rights, and access to benefits and equity)
* Integration into regional and national planning systems (including sea/landscape connectivity)
* Management system (for transboundary/serial properties, integrated management system should also be described/evaluated)
 */

function drush_iucn_migrate_iucn_update_protectiontopic($start = 0, $number = 10){
  drush_print(dt('Started: ' . date('h:i')));


  $exluded_sites = [
    116,
    2302,
    116,
    1801,
    152,
    151,
    28,
    81,
    179,
    187,
    207,
    2301,
    71,
    68,
    219,
    162,
    195,
    204,
    126,
    165,
  ];

  $start = drush_get_option('start', 0);
  $update = drush_get_option('update', FALSE);


  $assessments_path = \Drupal::config('iucn_migration.settings')->get('assessment_path');
  $assessments_file_content = file_get_contents($assessments_path);
  $assessments = Json::decode($assessments_file_content);
  if ($assessments) {



    for ($key = $start; $key < ($start + 10); $key++) {
      drush_print(dt('-----------------------key--------------------- ' . $key));
      if (!isset($assessments[$key])) {
        continue;
      }
      $assessment = $assessments[$key];

      $siteId = $assessment['siteId'];
      $assessmentCycle = $assessment['assessmentCycle'];

      if (in_array($siteId, $exluded_sites) && $assessmentCycle == 2017) {
        drush_print(dt('Skipping site: ' . $siteId));
        drush_print(dt('-----------------'));
        continue;
      }

      $site_ids = \Drupal::entityQuery('node')
        ->condition('field_site_id', $siteId)
        ->condition('type', 'site')
        ->execute();

      if ($site_ids) {
        $site_id = reset($site_ids);

        $site = Node::load($site_id);
        if (!$site) {
          drush_print(dt('Not found site: ' . $site_id));
          drush_print(dt('-----------------'));
          continue;
        }

        $site_assessment_ids = \Drupal::entityQuery('node')
          ->condition('field_as_site', $site->id())
          ->condition('type', 'site_assessment')
          ->condition('field_as_cycle', $assessmentCycle)
          ->execute();

        if ($site_assessment_ids) {
          $site_assessment_id = reset($site_assessment_ids);
          $site_assessment = Node::load($site_assessment_id);
          if (!$site_assessment) {
            drush_print(dt('Not found site_assessment: ' . $site_assessment_id));
            drush_print(dt('-----------------'));
            continue;
          }

        }
      }

      if (isset($assessment['versions']) && is_array($assessment['versions'])) {
        $assessments_versions = $assessment['versions'];
        usort($assessments_versions, function ($a, $b) {
          return ($a['versionCode'] > $b['versionCode']) ? -1 : 1;
        });

        $version = reset($assessments_versions);

        if ($site_assessment->get('field_as_version')->value != $version['versionCode']) {
          drush_print(dt('Different versions for assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' DB (' . $site_assessment->get('field_as_version')->value . ') JSON (' . $version['versionCode'] . ')'));
          drush_print(dt('-----------------'));
          continue;
        }

        // Process Assessing Protection and Management.
        drush_print(dt('Processing Assessing Protection and Management'));
        $paragraphs_updated = 0;
        if ($site_protections = $site_assessment->get('field_as_protection')->getValue()) {
          $paragraphs_db = [];
          foreach ($site_protections as $site_protection) {
            $paragraph = Paragraph::load($site_protection['target_id']);
            if ($paragraph) {
              $paragraphs_db[] = $paragraph;
            }
          }
          $paragraphs_json = [];
          foreach ($version['protectionManagement'] as $protectionManagement) {
            if ($protectionManagement["rating"]) {
              $paragraphs_json[] = $protectionManagement;
            }
          }

          if (count($site_protections) != count($version['protectionManagement'])) {
            if (count($paragraphs_db) != count($paragraphs_json)) {
              drush_print(dt('DB paragraphs count (' . count($site_protections) . ') did not match JSON count (' . count($version['protectionManagement']) . ') site assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' Please fix them manually !!!'));
              drush_print(dt('!!! AGAIN DB paragraphs count (' . count($paragraphs_db) . ') did not match JSON count (' . count($paragraphs_json) . ') site assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' Please fix them manually !!!'));
              drush_print(dt('-----------------'));
              continue;
            }
          }

          foreach ($paragraphs_db as $paragraph_db_key => $paragraph_db) {
            if ($update) {
              $paragraph_db->set('field_as_protection_topic', [assessmentTerm($paragraphs_json[$paragraph_db_key]['topic'], 'assessment_protection_topic')]);
              $paragraph_db->set('field_as_protection_rating', [assessmentTerm($paragraphs_json[$paragraph_db_key]['rating'], 'assessment_protection_rating')]);
              $paragraph_db->set('field_as_description', $paragraphs_json[$paragraph_db_key]['justification']);
              $paragraph_db->save();
              $paragraphs_updated++;
              drush_print(dt('Updated paragraph: ' . $paragraph_db->id() . ' topic: ' . $paragraphs_json[$paragraph_db_key]['topic'] . ' - ' . $paragraphs_json[$paragraph_db_key]['rating']));
            }
          }

          if ($update && $paragraphs_updated != 0) {
            $site_assessment->save();
            drush_print(dt('Updated assessment: ' . $site_assessment_id));
          }
          drush_print(dt('Done assessment: ' . $site_assessment_id));
          drush_print(dt('-----------------'));
        }

      }
    }
  }
  drush_print(dt('Completed: ' . date('h:i')));
}


/**
 Before running the command make sure to manually update the following taxonomies trems for Assessment threat extent:
 * Throughout(>50%)
 * Widespread(15-50%)
 * Scattered(5-15%)
 * Localised(<5%)
 */

function drush_iucn_migrate_iucn_update_threadextentvalue($start = 0, $number = 10){


  drush_print(dt('Started: ' . date('h:i')));

  $start = drush_get_option('start', 0);
  $update = drush_get_option('update', FALSE);


  $assessments_path = \Drupal::config('iucn_migration.settings')->get('assessment_path');
  $assessments_file_content = file_get_contents($assessments_path);
  $assessments = Json::decode($assessments_file_content);
  if ($assessments) {

    $paragraphs = [];

    for ($key = $start; $key < ($start + 10); $key++) {
      if (!isset($assessments[$key])) {
        continue;
      }
      $assessment = $assessments[$key];

      $siteId = $assessment['siteId'];
      $assessmentCycle = $assessment['assessmentCycle'];

      $site_ids = \Drupal::entityQuery('node')
        ->condition('field_site_id', $siteId)
        ->condition('type', 'site')
        ->execute();

      if ($site_ids) {
        $site_id = reset($site_ids);
        $site = Node::load($site_id);
        if (!$site) {
          drush_print(dt('Not found site: ' . $site_id));
          drush_print(dt('-----------------'));
          continue;
        }

        $site_assessment_ids = \Drupal::entityQuery('node')
          ->condition('field_as_site', $site->id())
          ->condition('type', 'site_assessment')
          ->condition('field_as_cycle', $assessmentCycle)
          ->execute();

        if ($site_assessment_ids) {
          $site_assessment_id = reset($site_assessment_ids);
          $site_assessment = Node::load($site_assessment_id);
          if (!$site_assessment) {
            drush_print(dt('Not found site_assessment: ' . $site_assessment_id));
            drush_print(dt('-----------------'));
            continue;
          }

        }
      }

      if (isset($assessment['versions']) && is_array($assessment['versions'])) {

        $assessments_versions = $assessment['versions'];
        usort($assessments_versions, function ($a, $b) {
          return ($a['versionCode'] > $b['versionCode']) ? -1 : 1;
        });

        $version = reset($assessments_versions);

        if ($site_assessment->get('field_as_version')->value != $version['versionCode']) {
          drush_print(dt('Different versions for assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' DB (' . $site_assessment->get('field_as_version')->value . ') JSON (' . $version['versionCode'] . ')'));
          drush_print(dt('-----------------'));
          continue;
        }

        // Process Current threats.
        drush_print(dt('Processing Current threats'));
        if ($site_currentThreats = $site_assessment->get('field_as_threats_current')->getValue()) {
          if (count($site_currentThreats) != count($version['assessingThreatsCurrent'])) {
            drush_print(dt('DB paragraphs (current threats) count (' . count($site_currentThreats) . ') did not match JSON count (' . count($version['assessingThreatsCurrent']) . ') site assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' Please fix them manually !!!'));
            drush_print(dt('-----------------'));
          }
          $paragraphs_updated = 0;
          foreach ($site_currentThreats as $site_currentThreat) {
            $paragraph = Paragraph::load($site_currentThreat['target_id']);
            if ($paragraph) {
              if (isset($version['assessingThreatsCurrent']) && is_array($version['assessingThreatsCurrent'])) {
                foreach ($version['assessingThreatsCurrent'] as $assessingThreats) {
                  if (isset($assessingThreats['threadextentvalue'])) {
                    if ($paragraph->get('field_as_threats_threat')->value == $assessingThreats['currentThreat']) {
                      if (!isset($paragraphs[$paragraph->id()])) {
                        $paragraphs[$paragraph->id()] = $paragraph->id();
                        if ($update) {
                          $paragraph->set('field_as_threats_extent', [assessmentThreatExtentTerm($assessingThreats['threadextentvalue'])]);
                          $paragraph->save();
                          drush_print(dt('Updated paragraph: ' . $paragraph->id() . ' threat extent: ' . $assessingThreats['threadextentvalue']));
                        }
                        $paragraphs_updated++;
                      }
                      else {
                        drush_print(dt('ERROR paragraph already fetched: ' . $paragraph->id()));
                      }
                    }
                  }
                }
              }


            }
            else {
              drush_print(dt('Not found paragraph: ' . $site_currentThreat['target_id']));
            }
          }
          if ($update && $paragraphs_updated != 0) {
            $site_assessment->save();
            drush_print(dt('Updated assessment: ' . $site_assessment_id));
          }
          drush_print(dt('Done assessment: ' . $site_assessment_id));
          drush_print(dt('-----------------'));
        }

        // Process Potential threats.
        drush_print(dt('Processing Potential threats'));
        if ($site_potentialThreats = $site_assessment->get('field_as_threats_potential')->getValue()) {
          if (count($site_potentialThreats) != count($version['assessingThreatsPotential'])) {
            drush_print(dt('DB paragraphs (potential threats) count (' . count($site_potentialThreats) . ') did not match JSON count (' . count($version['assessingThreatsPotential']) . ') site assessment id: ' . $site_assessment_id . ' json assessmentId: ' . $assessment['assessmentId'] . ' Please fix them manually !!!'));
            drush_print(dt('-----------------'));
          }
          $paragraphs_updated = 0;
          foreach ($site_potentialThreats as $site_potentialThreat) {
            $paragraph = Paragraph::load($site_potentialThreat['target_id']);
            if ($paragraph) {
              if (isset($version['assessingThreatsPotential']) && is_array($version['assessingThreatsPotential'])) {
                foreach ($version['assessingThreatsPotential'] as $assessingThreats) {
                  if (isset($assessingThreats['threadextentvalue'])) {
                    if ($paragraph->get('field_as_threats_threat')->value == $assessingThreats['potentialThreat']) {
                      if (!isset($paragraphs[$paragraph->id()])) {
                        $paragraphs[$paragraph->id()] = $paragraph->id();
                        if ($update) {
                          $paragraph->set('field_as_threats_extent', [assessmentThreatExtentTerm($assessingThreats['threadextentvalue'])]);
                          $paragraph->save();
                          drush_print(dt('Updated paragraph: ' . $paragraph->id() . ' threat extent: ' . $assessingThreats['threadextentvalue']));
                        }
                        $paragraphs_updated++;
                      }
                      else {
                        drush_print(dt('ERROR paragraph already fetched: ' . $paragraph->id()));
                      }
                    }
                  }
                }
              }


            }
            else {
              drush_print(dt('Not found paragraph: ' . $site_potentialThreat['target_id']));
            }
          }
          if ($update && $paragraphs_updated != 0) {
            $site_assessment->save();
            drush_print(dt('Updated assessment: ' . $site_assessment_id));
          }
          drush_print(dt('Done assessment: ' . $site_assessment_id));
          drush_print(dt('-----------------'));
        }
      }
    }
  }
  drush_print(dt('Completed: ' . date('h:i')));
}

/**
 * Checks and returns term id for assessment_threat_extent taxonomy.
 *
 * If the term is not found, it is created.
 */
function assessmentThreatExtentTerm($value = NULL) {

  if (empty($value) || $value == NULL) {
    return NULL;
  }
  if ($terms = taxonomy_term_load_multiple_by_name($value, 'assessment_threat_extent')) {
    $term = reset($terms);
  }
  else {
    $term = Term::create([
      'name' => $value,
      'vid' => 'assessment_threat_extent',
    ]);
    $term->save();
    drush_print(dt('!!! CREATED A NEW TERM: ' . $value));
  }
  return $term->id();
}

/**
 * Checks and returns term id for specifiv vid taxonomy.
 *
 * If the term is not found, it is created.
 */
function assessmentTerm($value = NULL, $vid = NULL) {
  if (empty($value) || $value == NULL) {
    return NULL;
  }
  if (empty($vid) || $vid == NULL) {
    return NULL;
  }
  if ($terms = taxonomy_term_load_multiple_by_name($value, $vid)) {
    $term = reset($terms);
  }
  else {
    $term = Term::create([
      'name' => $value,
      'vid' => $vid,
    ]);
    $term->save();
    drush_print(dt('!!! CREATED A NEW TERM (' . $vid . ') : ' . $value));
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

  if (!$year || !$action) {
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
            $node->save();
          }
        }
      }
    }
  }
  drush_print(dt("All done."));
}


