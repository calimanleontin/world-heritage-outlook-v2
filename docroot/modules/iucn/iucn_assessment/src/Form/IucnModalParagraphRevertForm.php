<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalParagraphRevertForm extends IucnModalParagraphForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [
      '#attributes' => ['class' => ['paragraph-form']],
      'warning' => [
        '#type' => 'markup',
        '#markup' => '<div class="revert-warning">' . $this->t('Are you sure you want to revert this row?') . '</div>',
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Revert'),
          '#ajax' => [
            'callback' => '::ajaxSave',
            'event' => 'click',
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
            'disable-refocus' => TRUE,
          ],
          '#attributes' => ['class' => ['button--primary']],
        ],
      ],
    ];
    $this->buildCancelButton($form);
    return $form;
  }

  public function ajaxSave(array $form, FormStateInterface $form_state) {
    $paragraph = $this->entity;
    $this->nodeRevision->get($this->fieldName)->appendItem(['entity' => $paragraph]);
    return parent::ajaxSave($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * The save() method is not used in ContentEntityConfirmFormBase. This
   * overrides the default implementation that saves the entity.
   *
   * Confirmation forms should override submitForm() instead for their logic.
   */
  public function save(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   *
   * The delete() method is not used in ContentEntityConfirmFormBase. This
   * overrides the default implementation that redirects to the delete-form
   * confirmation form.
   *
   * Confirmation forms should override submitForm() instead for their logic.
   */
  public function delete(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Override the default validation implementation as it is not necessary
    // nor possible to validate an entity in a confirmation form.
    return $this->entity;
  }

}
