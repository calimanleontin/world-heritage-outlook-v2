<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

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
    return $form;
  }

  public function getDiffMarkup($diff) {
    $diff_rows = [];
    foreach ($diff as $diff_group) {
      foreach ([0,2] as $i) {
        if (!empty($diff_group[$i + 1]['data']['#markup']) && !empty($diff_group[$i + 3]['data']['#markup'])
          && $diff_group[$i + 1]['data']['#markup'] == $diff_group[$i + 3]['data']['#markup']) {
          continue;
        }
        $diff_rows[] = [$diff_group[$i], $diff_group[$i + 1]];
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
            $key => $this->getCopyFieldValue($fieldValue),
          ],
        ],
      ],
    ];

    if (!empty($extraFieldName)) {
      $key2 = "{$extraFieldName}_{$vid}";
      $element['#key2'] = $key2;
      $element['#selector2'] = $this->getJsSelector($extraFieldName, $fieldWidgetType);
      if (!empty($extraFieldValue)) {
        $element['#attached']['drupalSettings']['diff'][$key2] = $this->getCopyFieldValue($extraFieldValue);
      }
    }

    return render($element);
  }

  /**
   * Retrieves the field type from the form element widget.
   *
   * @param $widget
   *  The widget of the field.
   *
   * @return string
   *  The field type.
   */
  public function getDiffFieldWidgetType($widget) {
    $type = '';
    if (!empty($widget['#type'])) {
      $type = $widget['#type'];
    }
    elseif (!empty($widget['value']['#type'])) {
      $type = $widget['value']['#type'];
    }
    elseif (!empty($widget[0]['value']['#type'])) {
      $type = $widget[0]['value']['#type'];
    }
    elseif (!empty($widget[0]['#entity_type'])) {
      $type = $widget[0]['#entity_type'];
    }
    return $type;
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
        $selector .= '-0-value';
        break;

      case "textfield":
        $selector .= '-0-value';
        break;

      case "checkboxes":
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
   * @param $fieldValue
   *  The Drupal-style value of the field.
   *
   * @return array|mixed
   *  The value which will be used by the JS script.
   */
  public function getCopyFieldValue($fieldValue) {
    $value = [];
    foreach ($fieldValue as $fv) {
      // todo check target_revision_id
      $value[] = !empty($fv['value']) ? $fv['value'] : $fv['target_id'];
    }
    if (count($value) == 1) {
      $value = reset($value);
    }
    return $value;
  }
}
