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

    if (empty($parentEntity)) {
      $parentEntity = \Drupal::routeMatch()->getParameter('node');
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

      }
    }

    $form['field_as_threats_extent']['#states'] = [
      'visible' => [
        ':input[data-drupal-selector="edit-field-as-threats-in-value"]' => ['checked' => TRUE],
      ],
    ];

    $form['actions']['submit']['#submit'][] = [self::class, 'updateAffectedValues'];
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
