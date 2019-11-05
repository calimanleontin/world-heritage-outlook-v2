<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_site\Plugin\Field\FieldFormatter\EntityReferenceListFormatter;
use Drupal\taxonomy\Entity\Term;


/**
 * Plugin implementation of the 'Term level list' field formatter.
 *
 * @FieldFormatter(
 *   id = "term_level_list",
 *   label = @Translation("Term level list"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class TermLevelList extends EntityReferenceListFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'level' => 1,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['level'] = [
      '#title' => t('Link label to the referenced entity'),
      '#type' => 'select',
      '#options' => [
        1 => $this->t('Level 1 (no parent)'),
        2 => $this->t('Level 2 (has parent)'),
      ],
      '#default_value' => $this->getSetting('level'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->getSetting('level') == 2 ? $this->t('Only terms with parents') : $this->t('Only parent terms');

    return $summary;
  }

  public function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);
    $entitiesPerLevel = [1 => [], 2 => []];

    $level = $this->getSetting('level') ?: 1;
    foreach ($entities as $idx => $entity) {
      if (!empty($entity->parent->target_id)) {
        $entitiesPerLevel[2][$entity->id()] = $entity;
        $entitiesPerLevel[1][$entity->parent->target_id] = Term::load($entity->parent->target_id);
      }
      else {
        $entitiesPerLevel[1][$entity->id()] = $entity;
      }
    }

    return $entitiesPerLevel[$level];
  }

}
