<?php

namespace Drupal\iucn_assessment\Plugin\diff\Field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;

/**
 * Plugin to diff core field types.
 *
 * @FieldDiffBuilder(
 *   id = "iucn_raw_value",
 *   label = @Translation("IUCN raw value diff"),
 *   field_types = {"decimal", "integer", "float", "email", "telephone",
 *     "date", "uri", "string", "timestamp", "created",
 *     "string_long", "language", "uuid", "map", "datetime", "boolean"
 *   },
 * )
 */
class IucnRawValue extends CoreFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = array();

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $value = $field_item->value;
        $value = Xss::filter($value);
        $value = trim($value);
        $result[$field_key][] = $value;
      }
    }

    return $result;
  }

}
