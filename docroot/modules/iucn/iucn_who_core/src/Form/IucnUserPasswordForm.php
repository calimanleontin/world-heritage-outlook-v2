<?php

namespace Drupal\iucn_who_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\UserPasswordForm;

/**
 * Provides a user password reset form.
 */
class IucnUserPasswordForm extends UserPasswordForm {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if (!empty($form_state->getErrors())) {
      $form_state->clearErrors();
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($account = $form_state->getValue('account')) {
      parent::submitForm($form, $form_state);
    }
    else {
      // Trick the user into believing the email was valid
      drupal_set_message($this->t('If you have registered an account, we\'ve sent you an email with further information to recover your password. Check your Inbox (and Spam folder)'));
      $form_state->setRedirect('user.page');
    }
  }
}
