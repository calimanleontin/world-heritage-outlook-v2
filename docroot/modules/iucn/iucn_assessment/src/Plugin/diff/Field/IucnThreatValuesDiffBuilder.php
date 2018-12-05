<?php

namespace Drupal\iucn_assessment\Plugin\diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff entity reference fields.
 *
 * @FieldDiffBuilder(
 *   id = "iucn_threat_values",
 *   label = @Translation("IUCN threat values"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   },
 * )
 */
class IucnThreatValuesDiffBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = array();
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        // Compare entity ids.
        if ($field_item->entity) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $field_item->entity;
          $result[$field_key][] = $entity->field_as_values_value->value;
        }
      }
    }

    return $result;
  }

}
