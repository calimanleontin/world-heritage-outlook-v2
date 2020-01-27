<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;

abstract class IucnModalDiffForm extends IucnModalParagraphForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attributes']['class'][] = 'diff-form';
    $form['#prefix'] = '<div id="drupal-modal" class="diff-modal">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';
    $form['#attached']['library'][] = 'iucn_backend/font-awesome';
    $form['info_content'] = [
      '#type' => 'markup',
      '#markup' => sprintf('<div class="messages messages--info"><div class="pull-right"><span class="legend-content-removed">%s</span><span class="legend-content-added">%s</span></div></div>',
        $this->t('Content removed'),
        $this->t('Content added')),
      '#weight' => -99,
    ];
    return $form;
  }

  public function getDiffMarkup($diff, $fieldType, $fieldValue = NULL) {
    $diff_rows = [];
    foreach ([0,2] as $i) {
      foreach ($diff as $diff_group) {
        if (empty($diff_group[$i + 1]['data']['#markup'])) {
          continue;
        }

        $current = $i + 1;
        $compared = $current == 1 ? 3 : 1;
        if (!empty($diff_group[$compared]['data']['#markup'])
          && strpos($diff_group[$compared]['data']['#markup'], $diff_group[$current]['data']['#markup']) !== FALSE) {
          $diff_group[$current]['class'] = 'diff-context';
        }
        $class = ($fieldType == 'string_long') ? ($i == 0 ? 'initial-row' : 'final-row') : 'diff-row';
        $diff_rows[] = [
          'class' => $class,
          'data' => [$diff_group[$i], $diff_group[$i + 1]],
        ];
      }
    }

    if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions']) && !empty($fieldValue)) {
      $initialCount = count($diff_rows) - count($fieldValue) - 1;
      if (!empty($diff_rows[$initialCount]['class'])) {
        $diff_rows[$initialCount]['class'] = 'initial-row';
      }
      if (!empty($diff_rows[$initialCount + 1])) {
        $diff_rows[$initialCount + 1]['class'] = 'final-row';
      }
    }
    return $diff_rows;
  }

  /**
   * @param $vid
   *  Assessment node revision id.
   * @param $fieldType
   *  Type of field (select, checkboxes, etc).
   * @param $fieldName
   *  The machine name of the field.
   * @param $fieldValue
   *  The field value which will be copied to the final version.
   * @param $extraFieldName
   *  The machine name of the extra field. Some field can be grouped and should use
   * the same copy button.
   * @param $extraFieldValue
   *  The value of the extra field which will be copied to the final version.
   *
   * @return
   *  The rendered element.
   */
  public function getCopyValueButton($vid, $fieldWidgetType, $fieldName, $fieldValue, $extraFieldName = NULL, $extraFieldValue = NULL) {
    $key = "{$fieldName}_{$vid}";

    $element = [
      '#theme' => 'assessment_diff_copy_button',
      '#type' => $fieldWidgetType,
      '#key' => $key,
      '#selector' => $this->getJsSelector($fieldName, $fieldWidgetType),
      '#attached' => [
        'drupalSettings' => [
          'diff' => [
            $key => $this->getCopyFieldValue($fieldName, $fieldValue),
          ],
        ],
      ],
    ];

    if (!empty($extraFieldName)) {
      $key2 = "{$extraFieldName}_{$vid}";
      $element['#key2'] = $key2;
      $element['#selector2'] = $this->getJsSelector($extraFieldName, $fieldWidgetType);
      if (!empty($extraFieldValue)) {
        $element['#attached']['drupalSettings']['diff'][$key2] = $this->getCopyFieldValue($fieldName, $extraFieldValue);
      }
    }

    return render($element);
  }

  /**
   * Retrieves the field type from the form element widget.
   *
   * @param array $form
   * @param string field
   *
   * @return string
   *  The field type.
   */
  public function getDiffFieldWidgetType(array $form, $field) {
    if (in_array($field, ParagraphAsSiteThreatForm::AFFECTED_VALUES_FIELDS)) {
      return 'checkboxes';
    }

    $widget = $form[$field]['widget'];
    if (!empty($widget['#type'])) {
      return $widget['#type'];
    }

    if (!empty($widget['value']['#type'])) {
      return $widget['value']['#type'];
    }

    if (!empty($widget[0]['value']['#type'])) {
      return $widget[0]['value']['#type'];
    }

    if (!empty($widget[0]['#type'])) {
      return $widget[0]['#type'];
    }

    \Drupal::logger('iucn_assessments')
      ->error(sprintf('Invalid widget found: %s', json_encode($form[$field]['widget'])));
  }

  /**
   * Builds a selector which can be used in javascript to retrieve field input.
   *
   * @param $fieldName
   *  The machine name of the field.
   * @param $fieldWidgetType
   *  Type of field (select, checkboxes, etc).
   *
   * @return string
   *  The JS selector.
   */
  public function getJsSelector($fieldName, $fieldWidgetType) {
    $selector = 'edit-' . str_replace('_', '-', $fieldName);
    switch ($fieldWidgetType) {
      case "textarea":
      case "text_format":
        $selector .= '-0-value';
        break;

      case "textfield":
        $selector .= '-0-value';
        break;

      case "checkboxes":
        if (in_array($fieldName, ParagraphAsSiteThreatForm::AFFECTED_VALUES_FIELDS)) {
          $selector .= '-select';
        }
        break;

      case "checkbox":
        $selector .= '-value';
        break;

      case "select":
        break;

      case "paragraph":
        $selector .= '-select';
        break;
    }
    return $selector;
  }

  /**
   * Retrieves the value which will be passed to JS settings.
   *
   * @param $fieldName
   * @param $fieldValue
   *  The Drupal-style value of the field.
   *
   * @return array|mixed
   *  The value which will be used by the JS script.
   */
  public function getCopyFieldValue($fieldName, $fieldValue) {
    $value = [];
    foreach ($fieldValue as $fv) {
      if (!is_array($fv)) {
        $value[] = $fv;
        continue;
      }

      foreach (['target_revision_id', 'target_id', 'value'] as $key) {
        if ($key == 'target_revision_id' && in_array($fieldName, ParagraphAsSiteThreatForm::AFFECTED_VALUES_FIELDS)) {
          continue;
        }

        if (array_key_exists($key, $fv)) {
          $value[] = $fv[$key];
          break;
        }
      }
    }
    if (count($value) <= 1) {
      $value = reset($value);
    }
    return $value;
  }

  protected function getFinalVersionLabel(Node $nodeRevision) {
    $helpText = 'Initial text in this row is the assessor\'s version';

    $helpTexts = [
      AssessmentWorkflow::STATUS_UNDER_COMPARISON => 'Initial text in this row is the coordinator\'s version sent out for review',
      AssessmentWorkflow::STATUS_FINAL_CHANGES => 'Initial text in this row is the references reviewer\'s version',
    ];

    $state = $nodeRevision->get('field_state')->value;
    if (!empty($helpTexts[$state])) {
      $helpText = $helpTexts[$state];
    }

    $title = [
      '#theme' => 'topic_tooltip',
      '#label' => $this->t('Final version'),
      '#help_text' => $this->t($helpText),
    ];

    return render($title);
  }
}
