<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Plugin implementation of the 'Parent category' field formatter..
 *
 * @FieldFormatter(
 *   id = "parent_category",
 *   label = @Translation("Parent category"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class ParentCategoryFormatter extends FormatterBase {

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
      if (empty($category->getName())) {
        continue;
      }
      $storage = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term');
      $parent = $storage->loadParents($category->id());
      $parent = reset($parent);
      $markup = !empty($parent) ? $parent->getName() : $category->getName();
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
    }
    return $element;
  }

}
