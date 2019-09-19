<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class CoordinatorPermissionsRoles extends IucnAssessmentTestBase {

  public function testNotAllowedEditHisRoles() {
    $this->userLogIn(TestSupport::COORDINATOR1);

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(
        [
          'mail' => TestSupport::COORDINATOR1,
        ]
      );

    $user = reset($user);
    $this->drupalGet($user->url('edit-form'));
    $this->assertElementNotPresent(".form-checkboxes");
  }

  public function testNotAllowedAssignRoles() {
    $this->userLogIn(TestSupport::COORDINATOR1);

    $this->canNotAssignRole(TestSupport::IUCN_MANAGER);
    $this->canNotAssignRole(TestSupport::ADMINISTRATOR);
    $this->canNotAssignRole(TestSupport::ASSESSOR1);
    $this->canNotAssignRole(TestSupport::REVIEWER1);
    $this->canNotAssignRole(TestSupport::REFERENCES_REVIEWER1);

    $this->drupalGet(Url::fromRoute('user.admin_create'));
    $this->canNotAssignRole();
  }

  /**
   * Check that coordinator cannot assign role for $user.
   * @param $userEmail
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function canNotAssignRole($userEmail = NULL) {
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

    $this->assertElementNotPresent('.form-item-roles-edit-world-heritage-site-assessments');
    $this->assertElementNotPresent('.form-item-roles-publish-world-heritage-site-assessments');
    $this->assertElementNotPresent('.form-item-roles-edit-content-pages');
    $this->assertElementNotPresent('.form-item-roles-publish-content-pages');
    $this->assertElementNotPresent('.form-item-roles-menu-editor');
    $this->assertElementNotPresent('.form-item-roles-edit-world-heritage-site-information');
    $this->assertElementNotPresent('.form-item-roles-edw-healthcheck-role');
    $this->assertElementNotPresent('.form-item-roles-publish-world-heritage-site-information');
    $this->assertElementNotPresent('.form-item-roles-manage-submissions');
  }
}
