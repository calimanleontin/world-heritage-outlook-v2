<?php

namespace Drupal\iucn_assessment;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;


class WHOTaxonomiesInstaller {

  static $vocabularies = [
    // whp_assessment_stages.json
    // whp_assessment_status.json
    'whp_benefit_checksubtype_lkp.json' => 'assessment_benefits_category',
    'whp_benefit_checktype_lkp.json' => 'assessment_benefits_category',
    'whp_benefit_rating_lkp.json' => 'assessment_benefits_rating',
    'whp_conservation_outlook_rating_lkp.json' => 'assessment_conservation_rating',
    'whp_key_conservation_scale_lkp.json' => 'assessment_key_conservation_scal',
    'whp_negative_factors_level_impact.json' => 'assessment_benefits_impact_level',
    'whp_negative_factors_trend.json' => 'assessment_benefits_impact_trend',
    'whp_protection_management_ratings_lkp.json' => 'assessment_protection_rating',
    'whp_protection_mgmt_checklist_lkp.json' => 'assessment_protection_topic',
    'whp_state_lkp.json' => 'assessment_value_state',
    'whp_threat_categories_lkp.json' => 'assessment_threat',
    'whp_threat_rating_lkp.json' => 'assessment_threat_level',
    'whp_threat_subcategories_lkp.json' => 'assessment_threat',
    'whp_trend_lkp.json' => 'assessment_value_trend',
  ];


  static function getTermByName($name, $machine_name) {
    if ($items = taxonomy_term_load_multiple_by_name($name, $machine_name)) {
      return current($items);
    }
    return NULL;
  }


  static function install() {
    foreach(self::$vocabularies as $file => $machine_name) {
      $voc = Vocabulary::load($machine_name);
      if (empty($voc)) {
        throw new \Exception("Taxonomy $machine_name not available, failing");
      }
      $path = file_get_contents(drupal_get_path('module', 'iucn_assessment') . '/data/taxonomy/' . $file);
      $terms = json_decode($path, TRUE);
      if (empty($file)) {
        throw new \Exception("Cannot load taxonomy file: $path not available, failing");
      }
      foreach($terms as $td) {
        if ($tmp = self::getTermByName($td['name'], $machine_name)) {
          \Drupal::logger(__CLASS__)->warning(
            'Term @term already exists, skipping ...',
            ['@term' => $td['name']]
          );
          continue;
        }
        // @todo implement field_tax_as_active for taxonomies and install 'active'
        // @todo fix weight field
        $arr = [
          'name' => $td['name'],
          'description' => $td['description'],
          'langcode' => 'en',
          'format' => filter_fallback_format(),
          'weight' => !empty($td['weight']) ? $td['weight'] : 0,
          'vid' => $machine_name,
        ];
        if (!empty($td['parent'])) {
          if ($parent = self::getTermByName($td['parent'], $machine_name)) {
            $arr['parent'] = [$parent->id()];
          }
        }
        $term = Term::create($arr);
        \Drupal::logger(__CLASS__)->debug(
          'Creating term @voc @term ...',
          ['@term' => $td['name'],
            '@voc' => $machine_name]
        );
        $term->save();
      }
    }
  }
}
