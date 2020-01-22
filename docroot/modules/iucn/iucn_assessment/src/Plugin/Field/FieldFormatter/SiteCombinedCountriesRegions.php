<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'site_combined_countries_regions' formatter.
 *
 * @FieldFormatter(
 *   id = "site_combined_countries_regions",
 *   label = @Translation("Display all IUCN/UNESCO regions for a site"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class SiteCombinedCountriesRegions extends EntityReferenceLabelFormatter {

  const FIELDS = [
    'field_iucn_region' => 'IUCN region',
    'field_unesco_region' => 'UNESCO region',
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'field' => key(static::FIELDS),
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['field'] = [
      '#title' => $this->t('Field'),
      '#type' => 'select',
      '#options' => static::FIELDS,
      '#default_value' => $this->getSetting('field'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Field: ') . static::FIELDS[$this->getSetting('field')];
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * Replace the direct referenced entity with the entities referenced from
   * children fields.
   */
  public function prepareView(array $entities_items) {
    $field = $this->getSetting('field');
    foreach ($entities_items as &$items) {
      $referencedItems = [];
      while($items->count()) {
        $item = $items->get(0);
        $items->removeItem(0);
        if (!$item instanceof EntityReferenceItem) {
          continue;
        }
        $entity = $item->entity;
        if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field)) {
          continue;
        }

        foreach ($entity->get($field) as $value) {
          /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
          $referencedEntity = $value->entity;
          $referencedItems[$referencedEntity->id()] = $referencedEntity;
        }
      }

      foreach ($referencedItems as $item) {
        $items->appendItem($item);
      }
    }

    parent::prepareView($entities_items);
  }

}
