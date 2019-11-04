<?php

namespace Drupal\role_hierarchy\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Class RoleHierarchyHelper provides core functions for role_hierarchy.
 *
 * @package Drupal\role_hierarchy
 */
class RoleHierarchyHelper {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->settings = $configFactory->get('role_hierarchy.settings');
  }

  /**
   * Get the lowest/highest user role weight, depending on settings.
   */
  public function getUserRoleWeight(User $user) {
    return $this->getAccountRoleWeight($user);
  }

  /**
   * Get the lowest/highest account role, depending on settings.
   */
  public function getAccountRoleWeight(AccountInterface $account) {
    $account_roles = Role::loadMultiple(array_values($account->getRoles()));

    if (!empty($this->settings->get('invert'))) {
      $result = max(array_map([__CLASS__, 'getRoleWeight'], $account_roles));
    }
    else {
      $result = min(array_map([__CLASS__, 'getRoleWeight'], $account_roles));
    }
    return $result;
  }

  /**
   * Get the weight of a role as configured in /admin/people/roles.
   *
   * @param Role|string $role
   *   The user role.
   *
   * @return int
   *   The weight of the role.
   */
  public function getRoleWeight($role) {
    if ($role == 'workflow_author') {
      return 9999;
    }

    if (is_string($role)) {
      $role = Role::load($role);
    }

    return $role->getWeight();
  }

  /**
   * Check if an user can edit another user by comparing their weights.
   *
   * @param $user_role_weight
   *   The user role weight.
   * @param $edited_role_weight
   *   The edited user role weight.
   *
   * @return bool
   */
  public function hasRoleEditAccess($user_role_weight, $edited_role_weight) {
    if (!empty($this->settings->get('invert'))) {
      if (!empty($this->settings->get('strict'))) {
        return $user_role_weight > $edited_role_weight;
      }
      else {
        return $user_role_weight >= $edited_role_weight;
      }
    }
    else {
      if (!empty($this->settings->get('strict'))) {
        return $user_role_weight < $edited_role_weight;
      }
      else {
        return $user_role_weight <= $edited_role_weight;
      }
    }
  }

  /**
   * Determines if an account has edit access on an user.
   *
   * @param AccountInterface $account
   *   The account that tries to edit.
   *
   * @param User $user
   *   The user.
   *
   * @return bool
   */
  public function hasEditAccess(AccountInterface $account, User $user) {
    if ($account->id() == 1) {
      return TRUE;
    }

    if ($account->id() == $user->id()) {
      return TRUE;
    }

    if ($user->id() == 1) {
      return FALSE;
    }
    $account_weight = $this->getAccountRoleWeight($account);
    $user_weight = $this->getUserRoleWeight($user);
    return $this->hasRoleEditAccess($account_weight, $user_weight);
  }

}
