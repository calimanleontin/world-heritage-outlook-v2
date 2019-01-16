<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;

abstract class IucnModalForm extends ContentEntityForm {

  public function getDiffFieldType($widget) {
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

  public function getJsSelector($fieldName, $type) {
    $selector = 'edit-' . str_replace('_', '-', $fieldName);
    switch ($type) {
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

  public function getCopyFieldValue($fieldValue) {
    $value = [];
    foreach ($fieldValue as $value) {
      // todo check target_revision_id
      $value[] = !empty($value['value']) ? $value['value'] : $value['target_id'];
    }
    if (count($value) == 1) {
      $value = reset($value);
    }
    return $value;
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
  public function getCopyValueButton($vid, $fieldType, $fieldName, $fieldValue, $extraFieldName = NULL, $extraFieldValue = NULL) {
    $key = "{$fieldName}_{$vid}";

    $element = [
      '#theme' => 'assessment_diff_copy_button',
      '#type' => $fieldType,
      '#key' => $key,
      '#selector' => $this->getJsSelector($fieldName, $fieldType),
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
      $element['#selector2'] = $this->getJsSelector($extraFieldName, $fieldType);
      if (!empty($extraFieldValue)) {
        $element['#attached']['drupalSettings']['diff'][$key2] = $this->getCopyFieldValue($extraFieldValue);
      }
    }

    return render($element);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // @TODO: fix problem with form is outdated.
    $form['#token'] = FALSE;

    // Define alternative submit callbacks using AJAX by copying the default
    // submit callbacks to the AJAX property.
    $submit = &$form['actions']['submit'];
    $submit['#ajax'] = [
      'callback' => '::ajaxSave',
      'event' => 'click',
      'progress' => [
        'type' => 'throbber',
        'message' => NULL,
      ],
    ];

    $form['actions']['submit']['#ajax']['disable-refocus'] = TRUE;
    self::buildCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSave(array $form, FormStateInterface $form_state) {
    return self::assessmentAjaxSave($form, $form_state);
  }

  public static function buildCancelButton(&$form) {
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'modal-cancel-button',
        ],
      ],
      '#ajax' => [
        'callback' => [self::class, 'closeModalForm'],
        'event' => 'click',
      ],
      '#limit_validation_errors' => [],
      '#submit' => [],
      '#weight' => 10,
    ];
  }

  public static function assessmentAjaxSave($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // When errors occur during form validation, show them to the user.
    if ($form_state->getErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#drupal-modal', $form));
    }
    else {
      // Get all necessary data to be able to correctly update the correct
      // field on the parent node.
      $route_match = \Drupal::routeMatch();
      $temporary_data = $form_state->getTemporary();
      $parent_entity_revision = isset($temporary_data['node_revision']) ?
        $temporary_data['node_revision'] :
        $route_match->getParameter('node_revision');
      $field_name = $route_match->getParameter('field');
      $field_wrapper_id = $route_match->getParameter('field_wrapper_id');

      // Refresh the paragraphs field.
      $response->addCommand(
        new HtmlCommand(
          $field_wrapper_id,
          \Drupal::service('entity.form_builder')
            ->getForm($parent_entity_revision, 'default')[$field_name]
        )
      );

      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

  /**
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function closeModalForm() {
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

}
