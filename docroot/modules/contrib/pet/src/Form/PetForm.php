<?php

namespace Drupal\pet\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * PetForm class.
 */
class PetForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['mimemail'] = [
      '#type' => 'details',
      '#title' => t('Mime Mail options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#open' => TRUE,
    ];
    $form['send_plain']['#group'] = 'mimemail';
    $form['mail_body_plain']['#group'] = 'mimemail';

    $form['mimemail']['#description'] = t('HTML email support is most easily provided by the <a href="@url">Mime Mail</a> module, which must be installed and enabled.', ['@url' => 'http://drupal.org/project/mimemail']);
    // @todo : #2366853 - Mime mail integration
    if (!pet_has_mimemail()) {
      unset($form['mail_body_plain']);
      unset($form['send_plain']);
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => t('Additional options'),
      '#open' => FALSE,
      '#access' => \Drupal::currentUser()
        ->hasPermission('administer previewable email templates'),
    ];
    $form['cc_default']['#group'] = 'advanced';
    $form['bcc_default']['#group'] = 'advanced';
    $form['from_override']['#group'] = 'advanced';
    $form['recipient_callback']['#group'] = 'advanced';
    $form['actions']['submit']['#value'] = t('Save Template');

    $form['tokens'] = pet_token_help();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\pet\Entity\Pet $pet */
    $pet = $this->entity;
    $form_state->setRedirect('entity.pet.collection');
    $status = $pet->save();
    $pet_title = ['%name' => $pet->label()];

    $messenger = \Drupal::messenger();
    if ($status == SAVED_UPDATED) {
      $messenger->addMessage($this->t('The email template %name has been updated.', $pet_title));
    }
    elseif ($status == SAVED_NEW) {
      $messenger->addMessage($this->t('The email template %name has been added.', $pet_title));
    }
  }

}
