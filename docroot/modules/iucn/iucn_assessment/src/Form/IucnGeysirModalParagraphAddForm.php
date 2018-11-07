<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geysir\Form\GeysirModalParagraphAddForm;

/**
 * Functionality to edit a paragraph through a modal.
 */
class IucnGeysirModalParagraphAddForm extends GeysirModalParagraphAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#ajax']['disable-refocus'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSave(array $form, FormStateInterface $form_state) {
    return IucnGeysirModalParagraphForm::assessmentAjaxSave($form, $form_state);
  }

}
