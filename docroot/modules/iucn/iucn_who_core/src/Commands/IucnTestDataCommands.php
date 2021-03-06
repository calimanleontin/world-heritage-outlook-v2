<?php

namespace Drupal\iucn_who_core\Commands;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
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
  public function createTestUsers($password = 'password', $numberOfUsers = 2) {
    /** @var \Drupal\user\Entity\Role[] $roles */
    $roles = Role::loadMultiple();
    foreach ($roles as $role) {
      for ($i = 1; $i <= $numberOfUsers; $i++) {
        $name = "{$role->id()}_{$i}";
        $user = user_load_by_name($name);
        if ($user instanceof UserInterface) {
          $user->setPassword($password);
        }
        else {
          $user = User::create([
            'name' => $name,
            'mail' =>  "{$name}@example.com",
            'pass' => $password,
            'status' => 1,
            'roles' => [$role->id()],
          ]);
        }
        $user->save();
      }
    }
  }

}
