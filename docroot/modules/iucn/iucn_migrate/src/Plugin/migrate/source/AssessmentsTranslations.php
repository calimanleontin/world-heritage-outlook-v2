<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Assessment translations.
 *
 * @MigrateSource(
 *   id = "assessments_translations"
 * )
 */
class AssessmentsTranslations extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    $new_items = [];
    foreach ($items as $item) {
      if (empty($item['translations'])) {
        continue;
      }
      foreach ($item['translations'] as $translation) {
        $translation['parent_assessment'] = $item;
        $new_items[] = $translation;
      }
    }
    return $new_items;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $assessment = $row->getSourceProperty('parent_assessment');
    foreach ($assessment as $key => $value) {
      // Set the fields not found on this version
      // from parent assessment version to the translation.
      if ($row->hasSourceProperty($key)) {
        continue;
      }
      $row->setSourceProperty($key, $value);
    }
    
    $this->setAssessmentRowTitle($row);

    $return = parent::prepareRow($row);

    return $return;
  }

}
