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

  public function getJsSelector($diff_field, $type) {
    $selector = 'edit-' . str_replace('_', '-', $diff_field);
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

  public function getCopyValueButton(&$form, $type, $data_value, $diff_field, $assessment_vid, $grouped_with = NULL) {
    if ((count($data_value) == 1) && ($type != 'checkboxes') && ($type != 'select')) {
      if (!empty($data_value[0]['value'])) {
        $value = $data_value[0]['value'];
      } elseif(!empty($data_value[0]['target_id'])) {
        $value = $data_value[0]['target_id'];
        // todo check target_revision_id
      }
    } else {
      $value = [];
      foreach($data_value as $data) {
        $value[] = $data['target_id'];
      }
    }
    $form['#attached']['drupalSettings']['diff'][$diff_field . '_' . $assessment_vid] = $value;

    $selector = $this->getJsSelector($diff_field, $type);

    $key2 = "";
    $selector2 = "";
    if ($grouped_with != $diff_field) {
      $key2 = $grouped_with . '_' . $assessment_vid;
      $selector2 = $this->getJsSelector($grouped_with, $type);
    }
    $element = [
      '#theme' => 'assessment_diff_copy_button',
      '#type' => $type,
      '#key' => "{$diff_field}_{$assessment_vid}",
      '#selector' => $selector,
      '#key2' => $key2,
      '#selector2' => $selector2,
    ];
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
          \Drupal::service('entity.form_builder')->getForm($parent_entity_revision, 'default')[$field_name]
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
