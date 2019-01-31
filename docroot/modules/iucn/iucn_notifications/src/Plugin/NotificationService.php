<?php

namespace Drupal\iucn_notifications\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\user\Entity\User;

/**
 * Class NotificationEmailPlugin.
 */
class NotificationService {

  const USER_ACCOUNT_ACTIVATED = 'USER_ACCOUNT_ACTIVATED';

  const USER_PASSWORD_RESET = 'USER_PASSWORD_RESET';

  /** Coordinator / assessor has been assigned to work on an assessment. */
  const WORKFLOW_SETUP_NEW_ASSESSMENT = 'SETUP_NEW_ASSESSMENT';

  /** The assessor has finished working on the assessment. */
  const WORKFLOW_INPUT_ASSESSMENT_DATA_BY_ASSESSORS = 'INPUT_ASSESSMENT_DATA_BY_ASSESSORS';

  /** A reviewer has been  */
  const WORKFLOW_REVIEW_BY_COORDINATOR = 'REVIEW_BY_COORDINATOR';

  /** email sent to the coordinator when state is "Review phase finished" */
  const WORKFLOW_ASSESSMENT_REVIEW_BY_REVIEWERS = 'ASSESSMENT_REVIEW_BY_REVIEWERS';

  /** email sent to administrators when state is "Ready to publish" */
  const WORKFLOW_FINAL_EDITS_BY_COORDINATOR = 'FINAL_EDITS_BY_COORDINATOR';

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  protected $petStorage;

  /** @var \Drupal\Core\Mail\MailManagerInterface */
  protected $mailManager;

  /** @var \Drupal\Core\Config\ConfigFactoryInterface */
  protected $configFactory;

  /** @var \Drupal\Core\Utility\Token */
  protected $token;

  /** @var \Drupal\Core\Logger\LoggerChannelInterface */
  protected $logger;

  /** @var \Drupal\Core\Session\AccountInterface */
  protected $currentUser;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, MailManagerInterface $mailManager, ConfigFactoryInterface $configFactory, Token $token, LoggerChannelFactoryInterface $loggerFactory, AccountInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->petStorage = $this->entityTypeManager->getStorage('pet');
    $this->mailManager = $mailManager;
    $this->configFactory = $configFactory;
    $this->token = $token;
    $this->logger = $loggerFactory->get('iucn_notifications');
    $this->currentUser = $currentUser;
  }

  /**
   * @param $notificationType
   * @param $userId
   * @param array $options
   *
   * @return bool
   */
  public function sendNotificationToUser($notificationType, $userId, $options = []) {
    $user = User::load($userId);
    return $this->sendNotification($notificationType, $user->getEmail(), $options);
  }

  /**
   * @param int $notificationType
   *  The type of the notification (e.g. NotificationService::SETUP_NEW_ASSESSMENT)
   * @param string|array $to
   *  Email recipient.
   * @param array $options
   *  An array of options required by pet_send_mail() plus:
   *    tokens - an array of token replacements
   *
   * @return bool
   */
  public function sendNotification($notificationType, $to, $options = []) {
    $pets = $this->petStorage->loadByProperties(['title' => $notificationType]);
    if (empty($pets)) {
      throw new \InvalidArgumentException('Invalid notification type');
    }
    $pet = reset($pets);

    if (!is_array($to)) {
      $to = [$to];
    }
    $success = TRUE;
    foreach ($to as $email) {
      $content = $pet->getMailbody();
      // include site wide tokens substitutions. Ex. current date
      $substitutions = [
        'globals' => NULL,
      ];
      if (!empty($options['tokens'])) {
        $substitutions = $options['tokens'];
      }
      $substitutions['globals'] = NULL;
      // if no user is passed, load user if TO email corresponds to a user
      if (!isset($substitutions['user'])) {
        $user = user_load_by_mail($to);
        if (!empty($user)) {
          $substitutions['user'] = $user;
        }
      }
      $subject = $this->token->replace($pet->getSubject(), $substitutions, ['clear' => TRUE]);
      $subject = htmlspecialchars_decode($subject);
      $content = $this->token->replace($content, $substitutions, ['clear' => TRUE]);

      if ($this->send('iucn_notifications_' . $pet->id(), $email, $subject, $content) == FALSE) {
        $this->logger->warning(t('@not to @to could not be sent.', [
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
    $lang_code = $this->currentUser->getPreferredLangcode();
    $from = $this->configFactory->get('system.site')->get('mail');
    $params['from'] = $from;
    $params['subject'] = $subject;
    $params['message'] = $content;
    $success = $this->mailManager->mail('iucn_notifications', $key, $to, $lang_code, $params, NULL, TRUE);
    if ($success == TRUE) {
      $this->logger->info('Succesfully sent e-mail from @from to @to with subject "@subject". (@key)', [
        '@from' => $from,
        '@to' => $to,
        '@subject' => $subject,
        '@key' => $key,
      ]);
    }
    else {
      $this->logger->error('Error sending e-mail to @to with subject "@subject". (@key)', [
        '@to' => $to,
        '@subject' => $subject,
        '@key' => $key,
      ]);
    }
    return $success;
  }
}
