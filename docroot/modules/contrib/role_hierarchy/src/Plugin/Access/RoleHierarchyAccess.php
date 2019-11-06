<?php

namespace Drupal\role_hierarchy\Plugin\Access;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\role_hierarchy\Service\RoleHierarchyHelper;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class used to set custom access on role_hierarchy functionality.
 */
class RoleHierarchyAccess implements ContainerInjectionInterface {

  /**
   * @var RoleHierarchyHelper
   */
  protected $roleHierarchyHelper;

  public function __construct(RoleHierarchyHelper $roleHierarchyHelper) {
    $this->roleHierarchyHelper = $roleHierarchyHelper;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('role_hierarchy.helper')
    );
  }

  /**
   * Users can only edit with an equal or lower hierarchical role.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of the user performing the edit.
   * @param \Drupal\user\Entity\User $user
   *   The user which is being edited.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The account is allowed/forbidden to edit an user.
   */
  public function accessEditUser(AccountInterface $account, User $user) {
    return AccessResult::allowedIf($this->roleHierarchyHelper->hasEditAccess($account, $user));
  }

}
