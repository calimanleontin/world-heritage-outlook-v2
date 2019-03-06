<?php

namespace Drupal\iucn_assessment\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\FieldDiffBuilderBase;

/**
 * Plugin to diff core field types.
 *
 * @FieldDiffBuilder(
 *   id = "strip_tags_field_diff_builder",
 *   label = @Translation("IUCN raw value diff"),
 *   field_types = {"decimal", "integer", "float", "email", "telephone",
 *     "date", "uri", "string", "timestamp", "created",
 *     "string_long", "language", "uuid", "map", "datetime", "boolean"
 *   },
 * )
 */
class StripTagsBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    foreach ($field_items as $field_key => $field_item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      if ($field_item->isEmpty() == FALSE) {
        $result[$field_key][] = trim(strip_tags(html_entity_decode($field_item->value), " \t\n\r\0\x0B\xC2\xA0"));
      }
    }

    return $result;
  }

}
