<?php

namespace Drupal\iucn_who_core\Commands;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

/**
 * Class IucnTestDataCommands
 *
 * @package Drupal\iucn_assessment\Commands
 */
class IucnTestDataCommands extends DrushCommands {

  /**
   * Create test users for each role.
   *
   * @param int $numberOfUsers
   *  Number of users to create for each role. Default is 2.
   *
   * @command iucn:create-test-users

   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTestUsers($numberOfUsers = 2) {
    /** @var \Drupal\user\Entity\Role[] $roles */
    $roles = Role::loadMultiple();
    foreach ($roles as $role) {
      for ($i = 1; $i <= $numberOfUsers; $i++) {
        $user = User::create([
          'name' => "{$role->id()}_{$i}@iucn.local",
          'mail' =>  "{$role->id()}_{$i}@iucn.local",
          'pass' => 'password',
          'status' => 1,
          'roles' => ['authenticated', $role->id()],
        ]);
        $user->save();
      }
    }
  }

}
