<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Base class for Assessments related migrations.
 */
abstract class AssessmentsBase extends SourceJson {

  /**
   * List of source field keys => values_set_identifier
   * which are paragraphs in our Drupal
   */
  public $paragraphsFieldsPropertyMap = [
    'assessmentWhvalues' => 'whvaluesId',
    'biodiversityValues' => 'id',
    'activeConservationProjects' => 'acpid',
    'potentialProjectNeeds' => 'ppnId',
    'benefits' => 'benefitsId',
    'protectionManagement' => 'pmId',
    'assessingThreatsCurrent' => 'currentThreatId',
    'assessingThreatsPotential' => 'potentialThreatId',
    'keyConservationIssues' => 'keyConservationIssuesId',
  ];

  public $paragraphsFieldsTypeMap = [
    'assessmentWhvalues' => 'as_site_value_wh',
    'biodiversityValues' => 'as_site_value_bio',
    'activeConservationProjects' => 'as_site_project',
    'potentialProjectNeeds' => 'as_site_project',
    'benefits' => 'as_site_benefit',
    'protectionManagement' => 'as_site_protection',
    'assessingThreatsCurrent' => 'as_site_threat',
    'assessingThreatsPotential' => 'as_site_threat',
    'keyConservationIssues' => 'as_site_key_conservation',
  ];

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    // Sort by revision id.
//    $items = array_slice($items, 0, 1);
    foreach ($items as $item_idx => &$item) {
      $item['langcode'] = $this->getVersionLang($item);

      if (empty($item['versions'])) {
        continue;
      }

      $item['translations'] = [];

      // Sort versions by version id.
      usort($item['versions'], function ($a, $b) {
        return ($a['assessmentVersionId'] < $b['assessmentVersionId']) ? -1 : 1;
      });
      if ($item['assessmentStatus'] == 'Completed' && in_array($item['currentStage'], ['Approved', 'Published'])) {
        $item['status'] = 1;
      }

      $paragraphs_fields_property_map = $this->paragraphsFieldsPropertyMap;

      reset($item['versions']);
      $latest_version = end($item['versions']);
      foreach ($item['versions'] as $key => &$version) {
        if (!empty($version['langVersion']['publishedDate'])) {
          $version['publishedDate'] = $version['langVersion']['publishedDate'];
        }
        // Those parag referencing parag move at the end.
        $temp = $version['assessingThreatsCurrent'];
        unset($version['assessingThreatsCurrent']);
        $version['assessingThreatsCurrent'] = $temp;

        $temp = $version['assessingThreatsPotential'];
        unset($version['assessingThreatsPotential']);
        $version['assessingThreatsPotential'] = $temp;

        foreach ($paragraphs_fields_property_map as $source_key => $identifier) {
          if (empty($version[$source_key])) {
            continue;
          }
          foreach ($version[$source_key] as &$version_set_data) {
            // Sort the data sets by the set id or by "vn".
            $version_set_data['delta_index'] = $version_set_data[$identifier];
            if (!empty($version_set_data['vn'])) {
              $version_set_data['delta_index'] = $version_set_data['vn'];
            }

            // Fix double mapping fields (same field multipel keys for different
            // sets of data (paragraphs).
            if (isset($version_set_data['summary'])) {
              $version_set_data['description'] = $version_set_data['summary'];
            }
            if (isset($version_set_data['justification'])) {
              $version_set_data['description'] = $version_set_data['justification'];
            }
            if (isset($version_set_data['potentialThreat'])) {
              $version_set_data['currentThreat'] = $version_set_data['potentialThreat'];
            }
          }
          // Sort set items.
          usort($version[$source_key], function ($a, $b) {
            return ($a['delta_index'] < $b['delta_index']) ? -1 : 1;
          });
        }
      }

      // Move translations from version array to translations.
      unset($version);
      foreach ($item['versions'] as $key => &$version) {
        $version['langcode'] = $this->getVersionLang($version);
        if ($item['baseLang'] != $version['langcode']) {
          $item['translations'][$version['langcode']] = $version;
          unset($item['versions'][$key]);
        }
      }

      unset($version);
      // Bubble up the set_id from previous version.
      foreach ($item['versions'] as $key => &$version) {
        foreach ($paragraphs_fields_property_map as $source_key => $identifier) {
          if (empty($version[$source_key])) {
            continue;
          }
          foreach ($version[$source_key] as $set_idx => &$version_set_data_2) {
            $version_set_data_2['setId'] = $version_set_data_2[$identifier];
            if (!empty($item['versions'][$key - 1][$source_key][$set_idx]['setId'])) {
              $version_set_data_2['setId'] = $item['versions'][$key - 1][$source_key][$set_idx]['setId'];
            }
            elseif (!empty($item['versions'][$key - 1][$source_key][$set_idx][$identifier])) {
              $version_set_data_2['setId'] = $item['versions'][$key - 1][$source_key][$set_idx][$identifier];
            }
          }
        }
      }

      // Loop again after reorder to set extra stuff to paragraphs fields.
      unset($version);
      unset($version_set_data_2);
      foreach ($item['versions'] as $key => &$version) {
        $assessment_id = $item['assessmentId'];
        if ($this->useRevisionIdForParagraphsLookup()) {
          $assessment_id = $version['assessmentVersionId'];
        }

        foreach ($paragraphs_fields_property_map as $source_key => $identifier) {
          if (empty($version[$source_key])) {
            continue;
          }

          foreach ($version[$source_key] as $set_idx => &$version_set_data_2) {
            $reference_to_values_curr = ['biodiversityValues', 'assessmentWhvalues'];
            // Move to currentStateTrend values the WH values.
            if (in_array($source_key, $reference_to_values_curr) && !empty($version['currentStateTrend'])) {
              foreach ($version['currentStateTrend'] as $curr_ref_set) {
                if (!empty($curr_ref_set['biodivValue']) && $source_key != 'biodiversityValues') {
                  continue;
                }
                $curr_identif = $source_key == 'biodiversityValues' ? 'biodivValue' : 'whValue';
                $ref_identif = $source_key == 'biodiversityValues' ? 'value' : 'values';
                if ($version_set_data_2[$ref_identif] == $curr_ref_set[$curr_identif]) {
                  $version_set_data_2['curr_text'] = $curr_ref_set['justification'];
                  $version_set_data_2['curr_trend'] = $curr_ref_set['trend'];
                  $version_set_data_2['curr_state'] = $curr_ref_set['state'];
                }
                // Fix double mapping for values and value.
                if ($source_key == 'biodiversityValues') {
                  $version_set_data_2['values'] = $version_set_data_2['value'];
                }
              }
            }

            $reference_to_wh_values_keys = ['assessingThreatsCurrent', 'assessingThreatsPotential'];
            if (in_array($source_key, $reference_to_wh_values_keys)) {
              if (!empty($version_set_data_2['affectedWhvalues'])) {
                foreach ($version_set_data_2['affectedWhvalues'] as $text_value) {
                  foreach ($version['assessmentWhvalues'] as $idx => $wh_value) {
                    if ($text_value == $wh_value['values']) {
                      $version_set_data_2['affectedWhvaluesIds'][] = [$wh_value['setId'], $assessment_id, 'assessmentWhvalues', 'as_site_value_wh'];
                    }
                  }
                }
              }
            }

            $reference_to_bio_values_keys = ['assessingThreatsCurrent', 'assessingThreatsPotential'];
            if (in_array($source_key, $reference_to_bio_values_keys)) {
              if (!empty($version_set_data_2['affectedOthervalues'])) {
                foreach ($version_set_data_2['affectedOthervalues'] as $text_value) {
                  foreach ($version['biodiversityValues'] as $idx2 => $bio_values) {
                    if ($text_value == $bio_values['value']) {
                      $version_set_data_2['biodiversityValuesIds'][] = [$bio_values['setId'], $assessment_id, 'biodiversityValues', 'as_site_value_bio'];
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    return $items;
  }

  /**
   * Decode language for an assessment source data array.
   */
  public function getVersionLang($version) {
    if (!empty($version['langVersion']['baseLang'])) {
      return $version['langVersion']['baseLang'];
    }
    if (!empty($version['baseLang'])) {
      return $version['baseLang'];
    }
    if (!empty($version['language'])) {
      $lang_codes = [1 => 'en', 2 => 'fr', 3 => 'es'];
      $lang_id = $version['language'];
      return $lang_codes[$lang_id];
    }

    return 'en';
  }

  public function setAssessmentRowTitle(Row $row) {
    $year = $row->getSourceProperty('assessmentCycle');
    $site_id = $row->getSourceProperty('siteId');
    $query = \Drupal::entityQuery('node')
      ->condition('field_site_id', $site_id);
    $nids = $query->execute();
    if (!empty($nids)) {
      $site_node = node_load(current($nids));
      $row->setSourceProperty('title', $year . ' ' . $site_node->getTitle());
    }
    else {
      $row->setSourceProperty('title', $year . ' ' . $site_id);
    }
  }

  /**
   * For an assessment row, set the paragraphs fields ids for migration lookup.
   */
  public function prepareParagraphReference(Row $row) {
    $assessment_id = $row->getSourceProperty('assessmentId');
    if ($this->useRevisionIdForParagraphsLookup()) {
      $assessment_id = $row->getSourceProperty('assessmentVersionId');
    }

    $paragraphs_fields_property_map = $this->paragraphsFieldsPropertyMap;

    $versions = $row->getSourceProperty('versions');
    if (empty($versions)) {
      return;
    }

    foreach ($paragraphs_fields_property_map as $source_key => $identifier) {
      $source_data = $row->getSourceProperty($source_key);
      if (empty($source_data)) {
        continue;
      }
      $source_data_ids = [];
      foreach ($source_data as $idx => $data) {
        $set_id = $data['setId'];
        $source_data_ids[] = [$set_id, $assessment_id, $source_key, $this->paragraphsFieldsTypeMap[$source_key]];
      }
      $row->setSourceProperty($source_key . 'Ids', $source_data_ids);
    }
  }

  public function useRevisionIdForParagraphsLookup() {
    return FALSE;
  }

}

