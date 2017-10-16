<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element\Item;


/**
 * Plugin implementation of the 'File description language'.
 *
 * @FieldFormatter(
 *   id = "pretty_subcategory",
 *   label = @Translation("Pretty subcategory"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class PrettySubcategoryFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      if (empty($item->entity)) {
        continue;
      }
      $category = $item->entity;
      $storage = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term');
      $parent = $storage->loadParents($category->id());
      $parent = reset($parent);
      if (empty($category->getName())) {
        continue;
      }
      $markup = $category->getName();
      if (!empty($parent)) {
        $markup = $parent->getName() . ' > ' . $markup;
      }
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => "$markup<br>",
      ];
    }
    return $element;
  }

}
