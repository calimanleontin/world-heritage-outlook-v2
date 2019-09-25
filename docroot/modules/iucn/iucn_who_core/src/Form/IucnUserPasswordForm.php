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
    // Do nothing here since we don't want to validate the username.
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = trim($form_state->getValue('name'));
    // Try to load by email.
    $users = $this->userStorage->loadByProperties(['mail' => $name]);
    if (empty($users)) {
      // No success, try to load by name.
      $users = $this->userStorage->loadByProperties(['name' => $name]);
    }
    $account = reset($users);
    if ($account && $account->id()) {
      // Blocked accounts cannot request a new password.
      if ($account->isActive()) {
        $langcode = $this->languageManager->getCurrentLanguage()->getId();

        $mail = _user_mail_notify('password_reset', $account, $langcode);
        if (!empty($mail)) {
          $this->logger('user')->notice('Password reset instructions mailed to %name at %email.', ['%name' => $account->getAccountName(), '%email' => $account->getEmail()]);
        }
      }
    }

    // Always set this message, even if the user doesn't exist.
    $this->messenger()->addStatus($this->t('If you have registered an account, we\'ve sent you an email with further information to recover your password. Check your Inbox (and Spam folder)'));
    $form_state->setRedirect('user.page');
  }

}
