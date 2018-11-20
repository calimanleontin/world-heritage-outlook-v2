<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;

abstract class IucnModalForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#prefix'] = '<div id="drupal-modal">';
    $form['#suffix'] = '</div>';

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
    $this->buildCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSave(array $form, FormStateInterface $form_state) {
    return self::assessmentAjaxSave($form, $form_state);
  }

  public static function buildCancelButton(&$form) {
    // Adding 'use-ajax' class will crash js.
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#attributes' => [
        'class' => [
          'modal-cancel-button',
        ],
      ],
      '#ajax' => [
        'callback' => [self::class, 'closeModalForm'],
        'event' => 'click',
      ],
      '#weight' => 10,
    ];
  }

  public static function assessmentAjaxSave($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // When errors occur during form validation, show them to the user.
    if ($form_state->getErrors()) {
      unset($form['#prefix']);
      unset($form['#suffix']);
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
      $node_revision = isset($temporary_data['node_revision']) ?
        $temporary_data['node_revision'] :
        $route_match->getParameter('node_revision');
      $field_name = $route_match->getParameter('field');
      $field_wrapper_id = $route_match->getParameter('field_wrapper_id');
      $parent_entity_revision = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($node_revision);

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

  public function buildCancelButton(&$form) {
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
      '#weight' => 10,
    ];
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
