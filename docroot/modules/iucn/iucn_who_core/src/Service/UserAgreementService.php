<?php

namespace Drupal\iucn_who_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;

class UserAgreementService {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  public function __construct(AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory) {
    $this->currentUser = $currentUser;
    $this->config = $configFactory->get('iucn_who_core.settings');
  }

  /**
   * Check if user accepted agreement.
   */
  public function userAcceptedAgreement() {
    $uid = $this->currentUser->id();
    $user = User::load($uid);

    return $user->field_accepted_agreement->value ||
      $user->hasRole('administrator') ||
      $user->field_user_agreement_disabled->value;
  }

  public function userCanSkipAgreement() {
    $uid = $this->currentUser->id();
    $user = User::load($uid);

    $roles = $user->getRoles(TRUE);

    foreach ($roles as $role) {
      $mustAcceptAgreement = $this->config->get(sprintf('agreement.%s.enabled', $role));
      if (!$mustAcceptAgreement) {
        return true;
      }
    }

    return FALSE;
  }

}
