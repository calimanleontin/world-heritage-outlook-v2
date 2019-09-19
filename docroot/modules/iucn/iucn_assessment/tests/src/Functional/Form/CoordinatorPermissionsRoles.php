<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\user\Entity\Role;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class CoordinatorPermissionsRoles extends IucnAssessmentTestBase {

  public function testUserAllowedToEditRole() {
    $invalidRoles = [
      'administrator',
      'edit_content_pages',
      'edit_world_heritage_site_assessments',
      'edit_world_heritage_site_information',
      'edw_healthcheck_role',
      'iucn_manager',
      'manage_submissions',
      'menu_editor',
      'publish_content_pages',
      'publish_world_heritage_site_assessments',
      'publish_world_heritage_site_information',
    ];

    $validRoles = [
      'assessor',
      'reviewer',
      'coordinator',
      'references_reviewer',
    ];

    $allRoles = array_keys(Role::loadMultiple());

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->checkRolesForEmail(
      [],
      $allRoles,
      TestSupport::IUCN_MANAGER
    );
    $this->checkRolesForEmail(
      [],
      $allRoles,
      TestSupport::ADMINISTRATOR
    );
    $this->checkRolesForEmail(
      $validRoles,
      $invalidRoles,
      TestSupport::ASSESSOR1
    );
    $this->checkRolesForEmail(
      $validRoles,
      $invalidRoles,
      TestSupport::REVIEWER1
    );
    $this->checkRolesForEmail(
      $validRoles,
      $invalidRoles,
      TestSupport::REFERENCES_REVIEWER1
    );
    $this->checkRolesForEmail(
      $validRoles,
      $invalidRoles,
      TestSupport::COORDINATOR2
    );

    $this->drupalGet(Url::fromRoute('user.admin_create'));
    $this->checkRolesForEmail($validRoles, $invalidRoles);
  }

  /**
   * @param $validRoles
   * @param $invalidRoles
   * @param string|null $userEmail
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkRolesForEmail(
    $validRoles,
    $invalidRoles,
    $userEmail = NULL
  ) {
    if (!empty($userEmail)) {
      $user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(
          [
            'mail' => $userEmail,
          ]
        );

      $user = reset($user);
      $this->drupalGet($user->url('edit-form'));
    }

    foreach ($invalidRoles as $role) {
      $cssSelector = "#edit-roles-" . str_replace('_', '-', $role);
      $this->assertElementNotPresent($cssSelector);
    }

    foreach ($validRoles as $role) {
      $cssSelector = "#edit-roles-" . str_replace('_', '-', $role);
      $this->assertElementPresent($cssSelector);
    }
  }
}
