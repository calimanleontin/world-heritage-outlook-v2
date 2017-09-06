<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldList;

use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Item list for a computed field that displays the current company.
 *
 * @see \Drupal\iucn_assessment\Plugin\Field\FieldType\SiteAssessmentRatingItem
 */
class SiteAssessmentRatingItemList extends FileFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->ensurePopulated();
    return new \ArrayIterator($this->list);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($include_computed = FALSE) {
    $this->ensurePopulated();
    return parent::getValue($include_computed);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensurePopulated();
    return parent::isEmpty();
  }

  /**
   * Makes sure that the item list is never empty.
   *
   * For 'normal' fields that use database storage the field item list is
   * initially empty, but since this is a computed field this always has a
   * value.
   * Make sure the item list is always populated, so this field is not skipped
   * for rendering in EntityViewDisplay and friends.
   *
   * @todo This will no longer be necessary once #2392845 is fixed.
   *
   * @see https://www.drupal.org/node/2392845
   */
  protected function ensurePopulated() {
    $entity = $this->getEntity();
    if ($entity->hasField('field_assessments')) {
      if ($entity->field_assessments->count()) {
        foreach ($entity->field_assessments as $idx => $item) {
          if (empty($item->entity) || isset($this->list[$idx])) {
            continue;
          }
          if (!empty($item->entity->field_as_global_assessment_level->entity)
            && !empty($item->entity->field_as_global_assessment_level->entity->field_image->target_id)) {
            $this->list[$idx] = $this->createItem($idx, $idx);
          }
        }
      }
    }

  }

}