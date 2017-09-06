<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Assessments revisions.
 *
 * @MigrateSource(
 *   id = "assessments_revisions"
 * )
 */
class AssessmentsRevisions extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    $new_items = [];
    foreach ($items as $item) {
      if (!empty($item['versions'])) {
        // Remove first one because was migrated in assessment migration.
        reset($item['versions']);
        array_shift($item['versions']);
        foreach ($item['versions'] as $version) {
          $version['parent_assessment'] = $item;
          unset($version['versions']);
          $new_items[] = $version;
        }
        // Mark last one as current rev and publish.
        end($new_items);
        $key = key($new_items);
        $new_items[$key]['current_rev'] = TRUE;
        // Publish latest revision if that's the case.
        if ($new_items[$key]['assessmentStage'] == 'Published') {
          $new_items[$key]['status'] = 1;
        }
        reset($new_items);
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
      // from parent assessment version.
      if ($row->hasSourceProperty($key)) {
        continue;
      }
      $row->setSourceProperty($key, $value);
    }

    $return = parent::prepareRow($row);

    $this->prepareParagraphReference($row);

    return $return;
  }

  public function useRevisionIdForParagraphsLookup() {
    return TRUE;
  }
}
