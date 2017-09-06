<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Assessments paragraphs.
 *
 * @MigrateSource(
 *   id = "assessments_paragraphs"
 * )
 */
class AssessmentsParagraphs extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    $paragraph_fields = $this->configuration['paragraphs_fields'];
    $paragraph_field_names = array_keys($paragraph_fields);

    $new_items = [];
    foreach ($items as $item) {
      if (empty($item['versions'])) {
        continue;
      }
      reset($item['versions']);

      // Collect all paragraphs from all versions by index.
      $paragraphs_current_item = [];
      $first_rev = (array) current($item['versions']);
      foreach ($item['versions'] as $version) {
        foreach ($version as $property => $set) {
          if (in_array($property, $paragraph_field_names)) {
            foreach ($set as $set_idx => $value) {
              if (!isset($paragraphs_current_item[$property][$set_idx])) {
                $paragraphs_current_item[$property][$set_idx] = TRUE;
                $new_item = $value;
                $new_item['type'] = $paragraph_fields[$property]['type'];
                $new_item['sourceKey'] = $property;
                $new_item['assessmentId'] = $item['assessmentId'];
                $new_item['assessmentVersionId'] = $first_rev['assessmentVersionId'];
                $new_item['langcode'] = $item['langcode'];
                $new_item['parentId'][] = [$new_item['setId'], $new_item['assessmentId'], $property, $new_item['type']];
                $new_items[] = $new_item;
              }
            }
          }
        }
      }

    }
    return $new_items;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $return = parent::prepareRow($row);

    return $return;
  }

  public function useRevisionIdForParagraphsLookup() {
    return FALSE;
  }
}
