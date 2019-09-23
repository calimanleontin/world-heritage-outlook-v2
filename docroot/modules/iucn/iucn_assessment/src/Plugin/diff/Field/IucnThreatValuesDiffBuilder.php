<?php

namespace Drupal\iucn_assessment\Plugin\diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff IUCN referenced values paragraphs.
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

    // Ignore delta when comparing.
    $values = $field_items->getValue();
    uasort($values, function ($a, $b) {
      if ($a['target_id'] == $b['target_id']) {
        return 0;
      }
      return ($a['target_id'] < $b['target_id']) ? -1 : 1;
    });
    $field_items->setValue($values);

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        // Compare field_as_values_value field.
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
