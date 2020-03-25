<?php

namespace Drupal\iucn_assessment;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\diff\FieldReferenceInterface;

/**
 * Overwrite the default diff entity parser. This is an enhancement to handle
 * paragraphs translations.
 */
class DiffEntityParser extends \Drupal\diff\DiffEntityParser {

  /**
   * {@inheritDoc}
   */
  public function parseEntity(ContentEntityInterface $entity) {
    $result = array();
    $entity_type_id = $entity->getEntityTypeId();
    // Loop through entity fields and transform every FieldItemList object
    // into an array of strings according to field type specific settings.
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
    foreach ($entity as $field_items) {
      // Define if the current field should be displayed as a diff change.
      $show_diff = $this->diffBuilderManager->showDiff($field_items->getFieldDefinition()->getFieldStorageDefinition());
      if (!$show_diff || !$entity->get($field_items->getFieldDefinition()->getName())->access('view')) {
        continue;
      }
      // Create a plugin instance for the field definition.
      $plugin = $this->diffBuilderManager->createInstanceForFieldDefinition($field_items->getFieldDefinition());
      if ($plugin) {
        // Create the array with the fields of the entity. Recursive if the
        // field contains entities.
        if ($plugin instanceof FieldReferenceInterface) {
          foreach ($plugin->getEntitiesToDiff($field_items) as $entity_key => $reference_entity) {
            $active_lancode = $entity->language()->getId();
            if ($reference_entity instanceof TranslatableInterface && $reference_entity->isTranslatable() && $reference_entity->hasTranslation($active_lancode)) {
              $reference_entity = $reference_entity->getTranslation($active_lancode);
            }
            foreach ($this->parseEntity($reference_entity) as $key => $build) {
              $result[$key] = $build;
              $result[$key]['label'] = $field_items->getFieldDefinition()->getLabel() . ' > ' . $result[$key]['label'];
            };
          }
        }
        else {
          $build = $plugin->build($field_items);
          if (!empty($build)) {
            $result[$entity->id() . ':' . $entity_type_id . '.' . $field_items->getName()] = $build;
            $result[$entity->id() . ':' . $entity_type_id . '.' . $field_items->getName()]['label'] = $field_items->getFieldDefinition()->getLabel();
          }
        }
      }
    }

    $this->diffBuilderManager->clearCachedDefinitions();
    return $result;
  }
}
