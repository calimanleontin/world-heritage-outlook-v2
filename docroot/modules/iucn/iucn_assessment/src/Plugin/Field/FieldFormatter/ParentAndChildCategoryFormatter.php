<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Plugin implementation of the 'Parent and child category' field formatter..
 * @FieldFormatter(
 *   id = "parent_and_child_category",
 *   label = @Translation("Parent and child category"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class ParentAndChildCategoryFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $childElements = [];
    $parentElement = NULL;
    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
    foreach ($items as $delta => $item) {
      if (empty($item->entity)) {
        continue;
      }
      /** @var \Drupal\taxonomy\TermInterface $category */
      $category = $item->entity;
      if (empty($category->label())) {
        continue;
      }
      $childElements[$delta] = $category->label();

      if (!empty($parentElement)) {
        continue;
      }

      $parent = $termStorage->loadParents($category->id());
      $parent = reset($parent);
      if (!empty($parent)) {
        $parentElement = $parent->label();
      }
    }

    ksort($childElements);
    $build = [
      [
        '#type' => 'markup',
        '#markup' => $parentElement,
        '#suffix' => ': ',
        '#access' => !empty($parentElement),
      ],
      [
        '#type' => 'markup',
        '#markup' => implode(', ', $childElements),
        '#access' => !empty($childElements),
      ],
    ];

    return $build;
  }

}
