<?php

namespace Drupal\iucn_who_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Plugin implementation of the 'Country region' field formatter.
 *
 * @FieldFormatter(
 *   id = "country_region",
 *   label = @Translation("Country region"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CountryRegionFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $regions = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\Taxonomy\TermInterface $entity */
      $country = $item->entity;
      /** @var \Drupal\Taxonomy\TermInterface $region */
      $region = $country->field_iucn_region->entity;
      if (empty($region)) {
        continue;
      }
      $regions[$region->id()] = $region->getName();
    }

    return [[
      '#markup' => implode(', ', $regions),
    ]];
  }

}
