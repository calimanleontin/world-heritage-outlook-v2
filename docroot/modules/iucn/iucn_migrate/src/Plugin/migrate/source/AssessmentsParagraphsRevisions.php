<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * A source class for Url resources.
 *
 * @MigrateSource(
 *   id = "assessments_paragraphs_revisions"
 * )
 */
class AssessmentsParagraphsRevisions extends AssessmentsBase {

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $items = parent::getSourceData($url);

    $paragraph_fields = $this->configuration['paragraphs_fields'];
    $paragraph_field_names = array_keys($paragraph_fields);

    $new_items = [];
    foreach ($items as $item) {
      // Skip if no assessment version.
      if (empty($item['versions'])) {
        continue;
      }
      reset($item['versions']);

      $latest_rev = end($item['versions']);

      foreach ($item['versions'] as $vidx => $version) {
        // Loop through keys and find the paragraphs keys.
        foreach ($version as $property => $set) {
          if (in_array($property, $paragraph_field_names)) {
            foreach ($set as $idx => $value) {
              // If item found on prev version, use that set id.
              $new_item = $value;
              $new_item['type'] = $paragraph_fields[$property]['type'];
              $new_item['sourceKey'] = $property;
              $new_item['assessmentId'] = $item['assessmentId'];
              $new_item['assessmentVersionId'] = $version['assessmentVersionId'];
              $new_item['langcode'] = $version['langcode'];
              $new_item['parentId'][] = [$new_item['setId'], $new_item['assessmentId'], $property, $new_item['type']];
              if ($version['assessmentVersionId'] == $latest_rev['assessmentVersionId']) {
                // Mark the last item as current revision.
                // @see \Drupal\iucn_migrate\EventSubscriber\MigrateSubscriber
                $new_item['current_rev'] = TRUE;
              }
              $new_items[] = $new_item;
            }
          }
        }
      }
    }
    return $new_items;
  }

  public function useRevisionIdForParagraphsLookup() {
    return TRUE;
  }

}
