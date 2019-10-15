<?php

namespace Drupal\iucn_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'text_tooltip' formatter.
 *
 * @FieldFormatter(
 *   id = "text_tooltip",
 *   label = @Translation("Tooltip"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   },
 *   quickedit = {
 *     "editor" = "form"
 *   }
 * )
 */
class TooltipFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme' => 'topic_tooltip',
        '#label' => null,
        '#help_text' => $this->t($item->value),
      ];
    }

    return $elements;
  }
}
