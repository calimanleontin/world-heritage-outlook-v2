<?php

namespace Drupal\iucn_who_core;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChangeRolePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns an array of role change permisions
   *
   * @return array
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function getPermissions() {
    $permissions = [];

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    /** @var \Drupal\user\Entity\Role $role */
    foreach ($roles as $role) {
      $permissions["change user role {$role->id()}"] = [
        'title' => $this->t("Change user role @role", [
          '@role' => $role->id(),
        ]),
        'description' => $this->t("Users with this role can assign/remove role @role from users", [
          '@role' => $role->id(),
        ]),
      ];
    }

    return $permissions;
  }
}
