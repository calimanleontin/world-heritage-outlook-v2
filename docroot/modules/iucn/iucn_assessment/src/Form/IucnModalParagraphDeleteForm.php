<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\linkit\ProfileInterface;
use Drupal\paragraphs\ParagraphInterface;

class IucnModalParagraphDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_paragraph_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['actions'] = ['#type' => 'actions'];

    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="delete-warning">' . $this->t('Are you sure you want to delete this row? This action cannot be reverted.') . '</div>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#ajax' => [
        'callback' => '::ajaxDelete',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
        'disable-refocus' => TRUE,
      ],
      '#attributes' => ['class' => ['button--primary']],
    ];

    IucnModalForm::buildCancelButton($form);

    return $form;
  }

  public function ajaxDelete(&$form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    /** @var ParagraphInterface $paragraph */
    $paragraph = $route_match->getParameter('paragraph_revision');
    $field = $route_match->getParameter('field');
    $parent_entity = $route_match->getParameter('node_revision');

    $field_values = $parent_entity->get($field)->getValue();
    $key = array_search($paragraph->id(), array_column($field_values, 'target_id'));
    $parent_entity->get($field)->removeItem($key);
    $parent_entity->save();
    return IucnModalForm::assessmentAjaxSave($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return [];
  }

}
