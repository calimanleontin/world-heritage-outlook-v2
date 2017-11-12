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
    $added = [];
    foreach ($items as $delta => $item) {
      if (empty($item->entity)) {
        continue;
      }
      /** @var \Drupal\taxonomy\TermInterface $category */
      $category = $item->entity;
      if (empty($category->getName())) {
        continue;
      }
      /** @var \Drupal\taxonomy\TermStorageInterface $storage */
      $storage = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term');
      $parent = $storage->loadParents($category->id());
      $parent = reset($parent);
      $markup = '';
      if (!empty($parent)) {
        if (!in_array($parent->id(), $added)) {
          $markup = $parent->getName();
          $added[] = $parent->id();
        }
      }
      else {
        $markup = $category->getName();
      }
      if (!empty($markup)) {
        $element[$delta] = [
          '#type' => 'markup',
          '#markup' => $markup,
        ];
      }
    }
    return $element;
  }

}
