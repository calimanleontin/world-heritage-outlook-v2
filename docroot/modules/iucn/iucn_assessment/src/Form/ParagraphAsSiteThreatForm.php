<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ParagraphAsSiteThreatForm {

  const AFFECTED_VALUES_FIELDS = [
    'field_as_threats_values_wh',
    'field_as_threats_values_bio',
  ];

  const FIELD_DEPENDENT_FIELDS = [
    'field_as_threats_extent' => 'field_as_threats_in',
  ];

  const SUBCATEGORY_DEPENDENT_FIELDS = [
    'field_as_legality' => [
      1384, // Hunting and trapping
      1386, // Logging/ Wood harvesting
      1387, // Fishing/ Harvesting aquatic resources
      1388, // Other biological resource use
      1433, // Non-timber forest products (NTFPs)
    ],
    'field_as_targeted_species' => [
      1384, // Hunting and trapping
      1386, // Logging/ Wood harvesting
      1387, // Fishing/ Harvesting aquatic resources
      1388, // Other biological resource use
      1433, // Non-timber forest products (NTFPs)
    ],
    'field_invasive_species_names' => [
      1395, // Invasive Non-Native/ Alien Species
      1396, // Hyper-Abundant Species
      1397, // Modified Genetic Material
      1434, // Diseases/pathogens
    ],
  ];

  const REQUIRED_DEPENDENT_FIELDS = [
    'field_as_legality',
  ];

  public static function alter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $formObject->getEntity();
    $parentEntity = $entity->getParentEntity();
    $route_match = \Drupal::routeMatch();

    // When adding new paragraphs, parent entity is not yet set.
    if (empty($parentEntity) && $route_match->getRouteName() == 'iucn_assessment.modal_paragraph_add') {
      $parentEntity = $route_match->getParameter('node_revision');
    }

    if ($parentEntity instanceof NodeInterface) {
      foreach (static::AFFECTED_VALUES_FIELDS as $field) {
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
        $form["{$field}_select_wrapper"] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'form-wrapper ' . $field . '_checkboxes',
            ],
          ],
          '#id' => 'edit-' . str_replace('_', '-', $field) . '-select',
          '#weight' => $formField['#weight'],
          "{$field}_select" => [
            '#type' => 'checkboxes',
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
        $form[$field]['#access'] = FALSE;

        if (empty($options)) {
          hide($form["{$field}_select_wrapper"]);
        }

      }
    }

    $specificThreatWidget = &$form['field_as_threats_threat']['widget'][0]['value'];
    $specificThreatWidget['#default_value'] = strip_tags($specificThreatWidget['#default_value']);

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

    foreach (static::SUBCATEGORY_DEPENDENT_FIELDS as $field => $tids) {
      $generic_selector = ':input[data-drupal-selector="edit-field-as-threats-categories-%tid"]';
      foreach ($tids as $idx => $tid) {
        $selector = str_replace('%tid', $tid, $generic_selector);
        $form[$field]['#states']['visible'][0][] = [$selector => ['checked' => TRUE]];
        if (in_array($field, static::REQUIRED_DEPENDENT_FIELDS)) {
          $form[$field]['widget'][0]['value']['#states']['required'][0][] = [$selector => ['checked' => TRUE]];
          $form[$field]['#states']['required'][0][] = [$selector => ['checked' => TRUE]];
          $form[$field]['widget']['#states']['required'][0][] = [$selector => ['checked' => TRUE]];
        }
      }
      // Required states API is bugged. It only shows the asterisk.
      $form[$field]['#element_validate'][] = [static::class, 'validateSubcategoryDependentField'];
    }

    $form['field_as_threats_extent']['#element_validate'][] = [static::class, 'validateThreatExtent'];
    $form['field_as_threats_categories']['#element_validate'][] = [static::class, 'validateThreatCategories'];

    array_unshift($form['actions']['submit']['#submit'], [static::class, 'setDependentFieldValues']);
    $form['actions']['submit']['#submit'][] = [static::class, 'updateAffectedValues'];

    $form['#validate'][] = [static::class, 'validateValues'];
  }

  public static function validateSubcategoryDependentField(array &$element, FormStateInterface $form_state, array &$form) {
    $field = $element['widget']['#field_name'];
    $title = $element['widget']['#title'];
    $selected_subcategories = $form_state->getValue('field_as_threats_categories');
    $selected_subcategories = array_column($selected_subcategories, 'target_id');
    if (empty($form_state->getValue($field)[0]['value'])
      && !empty(array_intersect(static::SUBCATEGORY_DEPENDENT_FIELDS[$field], $selected_subcategories))
      && in_array($field, static::REQUIRED_DEPENDENT_FIELDS)) {
      $form_state->setError($element, t('@field field is required', ['@field' => $title]));
    }
  }

  public static function setDependentFieldValues(array &$form, FormStateInterface $form_state) {
    foreach (static::FIELD_DEPENDENT_FIELDS as $field => $depends_on) {
      if (empty($form_state->getValue($depends_on)['value'])) {
        $form_state->setValue($field, []);
      }
    }

    $selected_subcategories = $form_state->getValue('field_as_threats_categories');
    $selected_subcategories = array_column($selected_subcategories, 'target_id');
    foreach (static::SUBCATEGORY_DEPENDENT_FIELDS as $field => $tids) {
      if (empty(array_intersect($tids, $selected_subcategories))) {
        $form_state->setValue($field, []);
      }
    }
  }

  public static function validateValues(array &$form, FormStateInterface $form_state) {
    $values_filled = FALSE;
    foreach (static::AFFECTED_VALUES_FIELDS as $field) {
      $values_selected = array_filter($form_state->getValue("{$field}_select"), function ($x) { return !empty($x); });
      if ((empty($form[$field]) && empty($form['diff']['edit'][$field])) || !empty($values_selected)) {
        // The field is not rendered on diff modal OR a value has been selected.
        $values_filled = TRUE;
        break;
      }
    }
    if (!$values_filled) {
      $form_state->setErrorByName('field_as_threats_values_wh_select', t('At least one affected value must be selected'));
      $form_state->setErrorByName('field_as_threats_values_bio_select', t('At least one affected value must be selected'));
    }

    $fieldThreatsInIsRendered = (!empty($form['field_as_threats_in']) || !empty($form['diff']['edit']['field_as_threats_in']));
    $fieldThreatsOutIsRendered = (!empty($form['field_as_threats_out']) || !empty($form['diff']['edit']['field_as_threats_out']));
    if ($fieldThreatsInIsRendered && $fieldThreatsOutIsRendered && empty($form_state->getValue('field_as_threats_in')['value']) && empty($form_state->getValue('field_as_threats_out')['value'])) {
      $form_state->setErrorByName('field_as_threats_in', t('At least one option must be selected for Inside site/Outside site'));
      $form_state->setErrorByName('field_as_threats_out', t('At least one option must be selected for Inside site/Outside site'));
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
    $selected_subcategory = FALSE;
    foreach ($values as $category) {
      if (!empty($element['widget']['options_groups']['#empty_groups'][$category['target_id']])
        || !isset($element['widget']['options_groups']['#options'][$category['target_id']])) {
        $selected_subcategory = TRUE;
        break;
      }
    }
    if (!$selected_subcategory) {
      $form_state->setError($element, t('Select at least one subcategory'));
    }
  }

  public static function updateAffectedValues(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphInterface $entity */
    $entity = $formObject->getEntity();

    foreach (static::AFFECTED_VALUES_FIELDS as $field) {
      $selected = $form_state->getValue("{$field}_select");
      $values = [];
      if (!empty($selected) && is_array($selected)) {
        foreach ($selected as $id => $isSelected) {
          if (empty($isSelected)) {
            continue;
          }
          $valueParagraph = Paragraph::load($id);
          if (empty($valueParagraph->id())) {
            continue;
          }
          $values[] = [
            'target_id' => $valueParagraph->id(),
            'target_revision_id' => $valueParagraph->getRevisionId(),
          ];
        }
      }
      $entity->set($field, $values);
    }

    $entity->save();
  }
}
