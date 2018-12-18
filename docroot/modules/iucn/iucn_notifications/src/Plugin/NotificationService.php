<?php

namespace Drupal\iucn_notifications\Plugin;

use Drupal\iucn_base\CssToInlineStyles\CssToInlineStyles;
use Drupal\pet\Entity\Pet;
use Drupal\pet\PetInterface;
use Drupal\swiftmailer\Plugin\Mail\SwiftMailer;

/**
 * Class NotificationEmailPlugin.
 */
class NotificationService {

  public static $USER_ACCOUNT_ACTIVATED = 'USER_ACCOUNT_ACTIVATED';

  public static $USER_PASSWORD_RESET = 'USER_PASSWORD_RESET';

  /** Coordinator has been assigned to a site */
  public static $WORKFLOW_SETUP_NEW_ASSESSMENT = 'SETUP_NEW_ASSESSMENT';

  /** email sent to the coordinators when state is "Assessment phase done" */
  public static $WORKFLOW_INPUT_ASSESSMENT_DATA_BY_ASSESSORS = 'INPUT_ASSESSMENT_DATA_BY_ASSESSORS';

  /** emails sent to each assigned reviewer when state is "Ready for review" */
  public static $WORKFLOW_REVIEW_BY_COORDINATOR = 'REVIEW_BY_COORDINATOR';

  /** email sent to the coordinator when state is "Review phase finished" */
  public static $WORKFLOW_ASSESSMENT_REVIEW_BY_REVIEWERS = 'ASSESSMENT_REVIEW_BY_REVIEWERS';

  /** email sent to administrators when state is "Ready to publish" */
  public static $WORKFLOW_FINAL_EDITS_BY_COORDINATOR = 'FINAL_EDITS_BY_COORDINATOR';

  /**
   * @param int $notificationType
   *  The type of the notification (e.g. NotificationEmail::SETUP_NEW_ASSESSMENT)
   * @param string|array $to
   *  Email recipient
   * @param array $options
   *  An array of options required by pet_send_mail() plus:
   *    tokens - an array of token replacements
   */
  public function sendNotification($notificationType, $to, $options = []) {
    $entities = \Drupal::entityTypeManager()->getStorage('pet')->loadByProperties(['title' => $notificationType]);
    if ($entities) {
      reset($entities);
      $entity = current($entities);
      $pet_id = $entity->id();
    }
    $pet = !empty($pet_id) ? Pet::load($pet_id) : NULL;
    if (!$pet instanceof PetInterface) {
      drupal_set_message(t('The email template is not configured for this notification.'), 'error');
      return NULL;
    }
    if (!is_array($to)) {
      $to = [$to];
    }
    $success = TRUE;
    foreach ($to as $email) {
      /** @var PetInterface $pet */
      $pet = Pet::load($pet_id);
      $content = $pet->getMailbody();
      $substitutions = [];
      if (!empty($options['tokens'])) {
        $substitutions = $options['tokens'];
      }
      // include site wide tokens substitutions. Ex. current date
      $substitutions['globals'] = NULL;
      // if no user is passed, load user if TO email corresponds to a user
      if (!isset($substitutions['user'])) {
        if ($user = user_load_by_mail($to)) {
          $substitutions['user'] = $user;
        }
      }
      $subject = \Drupal::token()->replace($pet->getSubject(), $substitutions, ['clear' => TRUE]);
      $subject = htmlspecialchars_decode($subject);
      $content = \Drupal::token()->replace($content, $substitutions, ['clear' => TRUE]);

      if ($this->send('iucn_notifications_' . $pet->id(), $email, $subject, $content) == FALSE) {
        \Drupal::logger('iucn_notifications')
          ->warning(t('@Not to @to could not be sent.', [
            '@not' => $notificationType,
            '@to' => $email,
          ]));
        $success = FALSE;
      }
    }
    return $success;
  }

  /**
   * send mail.
   */
  public function send($key, $to, $subject, $content) {
    $mailer = \Drupal::service('plugin.manager.mail');
    $lang_code = \Drupal::currentUser()->getPreferredLangcode();
    $from = \Drupal::config('system.site')->get('mail');
    $params['from'] = $from;
    $params['subject'] = $subject;
    $params['message'] = $content;
    $success = $mailer->mail('iucn_notifications', $key, $to, $lang_code, $params, NULL, TRUE);
    if ($success == TRUE) {
      \Drupal::logger('mail')
        ->info('Succesfully sent e-mail from @from to @to with subject "@subject". (@key)', [
          '@from' => $from,
          '@to' => $to,
          '@subject' => $subject,
          '@key' => $key,
        ]);
    }
    else {
      \Drupal::logger('mail')
        ->error('Error sending e-mail to @to with subject "@subject". (@key)', [
          '@to' => $to,
          '@subject' => $subject,
          '@key' => $key,
        ]);
    }
    return $success;
  }
}