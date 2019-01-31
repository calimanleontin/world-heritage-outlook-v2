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

  const USER_ACCOUNT_ACTIVATED = 'user_account_activated';

  const USER_PASSWORD_RESET = 'user_password_reset';

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
    $globalSuccess = TRUE;
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
      $success = $this->send('iucn_notifications_' . $notificationType, $email, $subject, $content);
      if ($success === FALSE) {
        $globalSuccess = FALSE;
      }
    }

    return $globalSuccess;
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
    $mailerResponse = $this->mailManager->mail('iucn_notifications', $key, $to, $lang_code, $params, NULL, TRUE);

    if (empty($mailerResponse['result'])) {
      $this->logger->error('Error sending e-mail to @to with subject "@subject". (@key)', [
        '@to' => $to,
        '@subject' => $subject,
        '@key' => $key,
      ]);
      return FALSE;
    }

    $this->logger->info('Succesfully sent e-mail from @from to @to with subject "@subject". (@key)', [
      '@from' => $from,
      '@to' => $to,
      '@subject' => $subject,
      '@key' => $key,
    ]);
    return TRUE;
  }
}
