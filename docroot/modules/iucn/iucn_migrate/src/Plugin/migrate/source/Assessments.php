<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Url resources.
 *
 * @MigrateSource(
 *   id = "iucn_assessments"
 * )
 */
class Assessments extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    // Use the first version (oldest) for the node
    // the following ones are migrated in assessments_revision migration.
    foreach ($items as &$item) {
      $versions = $item['versions'];
      if (!empty($versions)) {
        reset($versions);
        $first_version = (array) current($versions);
        foreach ($first_version as $key => $value) {
          if (empty($item[$key])) {
            $item[$key] = $value;
          }
        }
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $return = parent::prepareRow($row);

    $this->setAssessmentRowTitle($row);

    $this->prepareParagraphReference($row);

    return $return;
  }

  public function useRevisionIdForParagraphsLookup() {
    return FALSE;
  }

}
