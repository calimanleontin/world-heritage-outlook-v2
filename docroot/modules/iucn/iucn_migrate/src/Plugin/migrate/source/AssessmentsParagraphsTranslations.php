<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Assessments Paragraphs Translations.
 *
 * @MigrateSource(
 *   id = "assessments_paragraphs_translations"
 * )
 */
class AssessmentsParagraphsTranslations extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    $paragraph_fields = $this->configuration['paragraphs_fields'];
    $paragraph_field_names = array_keys($paragraph_fields);

    $new_items = [];
    foreach ($items as $item) {
      // SKip if no translations.
      if (empty($item['translations'])) {
        continue;
      }
      $latest_rev = end($item['versions']);
      foreach ($item['translations'] as $translation) {
        foreach ($translation as $property => $set) {
          // Loop through keys and find the paragraphs keys.
          if (in_array($property, $paragraph_field_names)) {
            foreach ($set as $idx => $value) {
              // @TODO solve this another way!?
              // If the latest version has fewer items,
              // skip the older extra ones.
              if (empty($latest_rev[$property][$idx]['setId'])) {
                continue;
              }
              $new_item = $value;
              $new_item['type'] = $paragraph_fields[$property]['type'];
              $new_item['setId'] = $latest_rev[$property][$idx]['setId'];
              $new_item['sourceKey'] = $property;
              $new_item['assessmentId'] = $item['assessmentId'];
              $new_item['assessmentVersionId'] = $translation['assessmentVersionId'];
              $new_item['langcode'] = $translation['langcode'];
              $new_item['parentId'][] = [$new_item['setId'], $new_item['assessmentId'], $property, $new_item['type']];
              $new_items[] = $new_item;
            }
          }
        }
      }
    }
    return $new_items;
  }

}
