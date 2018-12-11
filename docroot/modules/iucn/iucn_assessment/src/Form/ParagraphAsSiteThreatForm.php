<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ParagraphAsSiteThreatForm {

  const AFFECTED_VALUES_FIELDS = ['field_as_threats_values_wh', 'field_as_threats_values_bio'];

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $formObject->getEntity();
    $parentEntity = $entity->getParentEntity();
    $route_match = \Drupal::routeMatch();

    // When adding new paragraphs, parent entity is not yet set.
    if (empty($parentEntity) && $route_match->getRouteName() == 'iucn_assessment.modal_paragraph_add') {
      $revision_id = $route_match->getParameter('node_revision');
      $parentEntity = \Drupal::service('iucn_assessment.workflow')->getAssessmentRevision($revision_id);
    }

    if ($parentEntity instanceof NodeInterface) {
      foreach (self::AFFECTED_VALUES_FIELDS as $field) {
        $parentFieldName = str_replace('as_threats_values', 'as_values', $field);
        $parentField = $parentEntity->{$parentFieldName};
        if (!$parentField instanceof EntityReferenceRevisionsFieldItemList) {
          continue;
        }

        $options = [];
        foreach ($parentField->getValue() as $value) {
          $valueParagraph = Paragraph::load($value['target_id']);
          if (empty($valueParagraph->id()) || empty($valueParagraph->field_as_values_value->value)) {
            continue;
          }

          $options[$valueParagraph->id()] = $valueParagraph->field_as_values_value->value;
        }

        $formField = &$form[$field];
        $form["{$field}_select"] = [
          '#type' => 'select',
          '#title' => !empty($formField['widget']['title']['#value'])
            ? $formField['widget']['title']['#value']
            : $form[$field]['widget']['#title'],
          '#multiple' => TRUE,
          '#options' => $options,
          '#default_value' => array_column($entity->{$field}->getValue(), 'target_id'),
          '#chosen' => FALSE,
        hide($formField['widget']);
        $form["{$field}_select_wrapper"] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-wrapper']],
          '#weight' => $formField['#weight'],
          "{$field}_select" => [
            '#type' => 'select',
            '#title' => !empty($formField['widget']['title']['#value'])
              ? $formField['widget']['title']['#value']
              : $form[$field]['widget']['#title'],
            '#multiple' => TRUE,
            '#options' => $options,
            '#default_value' => array_column($entity->{$field}->getValue(), 'target_id'),
            '#chosen' => FALSE,
            '#size' => max(count($options), 5),
          ],
        ];
        unset($formField['widget']);

        if (empty($options)) {
          hide($form["{$field}_select"]);
        }

      }
    }

    $form['field_as_threats_extent']['#states'] = [
      'visible' => [
        ':input[data-drupal-selector="edit-field-as-threats-in-value"]' => ['checked' => TRUE],
      ],
    ];

    $form['field_as_threats_extent']['widget']['#states'] = [
      'required' => [
        ':input[data-drupal-selector="edit-field-as-threats-in-value"]' => ['checked' => TRUE],
      ],
    ];

    $form['field_as_threats_extent']['#element_validate'][] = [self::class, 'validateThreatExtent'];
    $form['field_as_threats_categories']['#element_validate'][] = [self::class, 'validateThreatCategories'];

    $form['actions']['submit']['#submit'][] = [self::class, 'updateAffectedValues'];

    $form['#validate'][] = [self::class, 'validateValues'];
  }

  public static function validateValues(array &$form, FormStateInterface $form_state) {
    $values_filled = FALSE;
    foreach (self::AFFECTED_VALUES_FIELDS as $field) {
      if (!empty($form_state->getValue("{$field}_select"))) {
        $values_filled = TRUE;
        break;
      }
    }
    if (!$values_filled) {
      $form_state->setErrorByName('affected_values', t('At least one affected value must be selected'));
    }

    if (empty($form_state->getValue('field_as_threats_in')['value']) && empty($form_state->getValue('field_as_threats_out')['value'])) {
      $form_state->setErrorByName('threat_in_out', t('At least one option must be selected for Inside site/Outside site'));
    }
  }

  public static function validateThreatExtent(array &$element, FormStateInterface $form_state, array &$form) {
    if (!empty($form_state->getValue('field_as_threats_in')['value']) && empty($form_state->getValue('field_as_threats_extent'))) {
      $form_state->setError($element, t('Threat extent field is required'));
    }
  }

  public static function validateThreatCategories(array &$element, FormStateInterface $form_state, array &$form) {
    $values = $form_state->getValue('field_as_threats_categories');
    if (empty($values) || count($values) == 1 && $values[0]['target_id'] == 0 ) {
      $form_state->setError($element, t('Category field is required'));
    }
    $selected_category = FALSE;
    foreach ($values as $category) {
      if (!isset($element['widget']['options_groups']['#options'][$category['target_id']])) {
        $selected_category = TRUE;
      }
    }
    if (!$selected_category) {
      $form_state->setError($element, t('Select at least one subcategory'));
    }
  }

  public static function updateAffectedValues(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $formObject->getEntity();

    foreach (self::AFFECTED_VALUES_FIELDS as $field) {
      $selected = $form_state->getValue("{$field}_select");
      if (!empty($selected) && is_array($selected)) {
        $values = [];
        foreach ($selected as $target_id) {
          $valueParagraph = Paragraph::load($target_id);
          if (empty($valueParagraph->id())) {
            continue;
          }
          $values[] = [
            'target_id' => $valueParagraph->id(),
            'target_revision_id' => $valueParagraph->getRevisionId(),
          ];
        }
        $entity->set($field, $values);
      }
    }

    $entity->save();
  }
}
