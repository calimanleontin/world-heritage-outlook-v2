<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalParagraphAcceptForm extends IucnModalParagraphConfirmationForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['warning']['#value'] = $this->t('Are you sure you want to accept this row?');
    $form['actions']['submit']['#value'] = $this->t('Accept');
    return $form;
  }

  public function ajaxSave(array $form, FormStateInterface $form_state) {
    $paragraph = $this->entity;
    $this->nodeRevision->get($this->fieldName)->appendItem(['entity' => $paragraph]);
    return parent::ajaxSave($form, $form_state);
  }

}
