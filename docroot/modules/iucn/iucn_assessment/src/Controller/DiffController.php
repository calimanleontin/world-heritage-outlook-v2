<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Revision comparison service that prepares a diff of a pair of revisions.
 */
class DiffController extends ControllerBase {

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\diff\DiffEntityComparison */
  protected $entityComparison;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    // Can't add it to arguments list in services.yml because of the following error:
    // The service "iucn_assessment.diff_controller" has a dependency on a non-existent service "diff.entity_comparison".
    $this->entityComparison = \Drupal::service('diff.entity_comparison');
  }

  public function compareRevisions($vid1, $vid2) {
    $revision1 = $this->nodeStorage->loadRevision($vid1);
    $revision2 = $this->nodeStorage->loadRevision($vid2);

    if (!$revision1 instanceof NodeInterface || !$revision2 instanceof NodeInterface) {
      throw new \InvalidArgumentException('Invalid revisions ids.');
    }
    if ($revision1->id() != $revision2->id()) {
      throw new \InvalidArgumentException('Can only compare 2 revisions of same node.');
    }

    $fields = $this->entityComparison->compareRevisions($revision1, $revision2);

    $diff = [];
    foreach ($fields as $key => $field) {
      if (preg_match('/(\d+)\:(.+)\.(.+)/', $key, $matches)) {
        $this->entityComparison->processStateLine($field);
        $field_diff_rows = $this->entityComparison->getRows(
          $field['#data']['#left'],
          $field['#data']['#right']
        );
        if (!empty($field_diff_rows)) {
          $entityId = $matches[1];
          $entityType = $matches[2];
          $fieldName = $matches[3];
          $diff[$entityType][$entityId]['initial_revision_id'] = $vid1;

          $entity = $this->entityTypeManager()->getStorage($entityType)->load($entityId);
          if ($this->isBooleanField($entity, $fieldName)) {
            $field_diff_rows[0][0] = $field_diff_rows[0][2];
            $field_diff_rows[0][0]['data'] = '';
            $field_diff_rows[0][0]['class'] .= ' diff-tick-marker';
            $field_diff_rows[0][1] = $field_diff_rows[0][3];
            if ($this->isBooleanFieldTrue($entity, $fieldName, $field['#data']['#right'][0])) {
              $field_diff_rows[0][1]['class'] .= ' diff-tick-true';
            }
            else {
              $field_diff_rows[0][1]['class'] .= ' diff-tick-false';
            }
            unset($field_diff_rows[0][2]);
            unset($field_diff_rows[0][3]);
          }

          if ($entityType == 'node') {
            $field_group_id = $this->getFieldGroupIdForNodeField($fieldName);
          }
          elseif ($entityType == 'paragraph') {
            $field_group_id = $this->getFieldGroupIdForParagraphField($entityId, $fieldName);
          }

          if (!empty($field_group_id) && empty($diff['fieldgroups'][$field_group_id])) {
            $diff['fieldgroups'][$field_group_id] = $field_group_id;
          }

          $diff[$entityType][$entityId]['diff'][$fieldName] = $field_diff_rows;
        }
      }
      else {
        $this->getLogger('iucn_diff')->error('Invalid field diff key.');
      }
    }
    return $diff;
  }

  /**
   * Gets the ID of the field_group nesting a field.
   *
   * @param $field
   *   The field.
   *
   * @return string|null
   *   The group id.
   */
  public function getFieldGroupIdForNodeField($field) {
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.site_assessment.default');
    $field_group_settings = $form_display->getThirdPartySettings('field_group');
    foreach ($field_group_settings as $key => $settings) {
      if (in_array($field, $settings['children'])) {
        return $settings['parent_name'] != 'group_as_tabs'
          ? $this->getFieldGroupIdForNodeField($key)
          : str_replace('_', '-', $settings['format_settings']['id']);
      }
    }
    return NULL;
  }

  /**
   * Get the tab where a paragraph field is found.
   *
   * These values need to be hardcoded because the diff module returns
   * no information regarding the parent field of an entity reference field.
   *
   * @param $paragraph_id
   * @param $field
   * @return null|string
   */
  public function getFieldGroupIdForParagraphField($paragraph_id, $field) {
    $paragraph = Paragraph::load($paragraph_id);
    switch ($paragraph->bundle()) {
      case 'as_site_value_wh':
      case 'as_site_value_bio':
        $components = EntityFormDisplay::load("paragraph.{$paragraph->bundle()}.default")->getComponents();
        if (in_array($field, $components)) {
          return 'values';
        }
        return 'assessing-values';

      case 'as_site_threat':
        return 'threats';

      case 'as_site_protection':
        return 'protection-management';

      case 'as_site_benefit':
        return 'benefits';

      case 'as_site_project':
        return 'projects';

      case 'as_site_reference':
        return 'references';

      default:
        return NULL;
    }
  }

  public function isBooleanField(EntityInterface $entity, $field_name) {
    $bundle = $entity->bundle();
    $field_config = FieldConfig::loadByName($entity->getEntityTypeId(), $bundle, $field_name);
    return $field_config->getType() == 'boolean';
  }

  public function isBooleanFieldTrue(EntityInterface $entity, $field_name, $compare_value) {
    $bundle = $entity->bundle();
    $on_label = FieldConfig::loadByName($entity->getEntityTypeId(), $bundle, $field_name)->getSetting('on_label');
    return $compare_value == $on_label;
  }

}
