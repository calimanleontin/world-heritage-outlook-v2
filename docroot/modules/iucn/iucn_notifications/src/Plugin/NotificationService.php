<?php

namespace Drupal\iucn_notifications\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Token;
use Drupal\pet\Entity\Pet;
use Drupal\pet\PetInterface;
use Drupal\user\Entity\User;

/**
 * Class NotificationEmailPlugin.
 */
class NotificationService {

  const USER_ACCOUNT_ACTIVATED = 'user_account_activated';

  const USER_PASSWORD_RESET = 'user_password_reset';

  /** @var \Drupal\Core\Database\Connection */
  protected $database;

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

  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager, MailManagerInterface $mailManager, ConfigFactoryInterface $configFactory, Token $token, LoggerChannelFactoryInterface $loggerFactory, AccountInterface $currentUser) {
    $this->database = $database;
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
    if (empty($user)) {
      $this->logger->error("NotificationService::sendNotificationToUser: invalid user id {$userId}, notification could not be sent.");
      return FALSE;
    }
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
    $pet = $this->getPetByTitle($notificationType);
    if (!$pet instanceof PetInterface) {
      throw new \InvalidArgumentException('Invalid notification type');
    }

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

      // Tokens with no replacement value will be removed.
      $options = [
        'clear' => TRUE,
      ];
      // For password reset tokens we need to add user_mail_tokens() as a callback.
      if (in_array($notificationType, [NotificationService::USER_PASSWORD_RESET, NotificationService::USER_ACCOUNT_ACTIVATED])) {
        $options['callback'] = 'user_mail_tokens';
      }

      $content = $this->token->replace($content, $substitutions, $options);
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
    $params['Bcc'] = Settings::get('who_archive_email');
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

  /**
   * Helper function because loading pets using entity query no longer works
   * after 8.7.x core update.
   *
   * @param $title
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\pet\Entity\Pet|null
   */
  public function getPetByTitle($title) {
    /** @var \Drupal\Core\Database\Statement $statement */
    $statement = $this->database->select('pet_field_data', 'pfd')
      ->fields('pfd', ['id'])
      ->condition('pfd.title', $title)
      ->execute();
    $id = $statement->fetchField();
    return !empty($id) ? Pet::load($id) : NULL;
  }
}
