<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class EditProfilePermissionsAdjustmentsTest extends IucnAssessmentTestBase {

  /**
   * Check that assessors, reviewer and references reviewer cannot edit email and they don't view their title.
   */
  public function testNotAllowedEditPermission() {
    $this->isNotAllowed(TestSupport::REVIEWER1);
    $this->isNotAllowed(TestSupport::REFERENCES_REVIEWER1);
    $this->isNotAllowed(TestSupport::ASSESSOR1);
  }

  /**
   * Check that administrator, iucn manager and coordinator can edit email and view their title.
   */
  public function testAllowedEditPermission() {
    $this->isAllowed(TestSupport::ADMINISTRATOR);
    $this->isAllowed(TestSupport::IUCN_MANAGER);
    $this->isAllowed(TestSupport::COORDINATOR1);
  }

  public function isAllowed($userType) {
    $this->userLogIn($userType);

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(
        [
          'mail' => $userType,
        ]
      );

    $user = reset($user);
    $this->drupalGet($user->url('edit-form'));
    $this->assertElementPresent('.field--name-field-user-title');
    $this->assertSession()->fieldEnabled('edit-mail');
  }

  public function isNotAllowed($userType) {
    $this->userLogIn($userType);

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(
        [
          'mail' => $userType,
        ]
      );

    $user = reset($user);
    $this->drupalGet($user->url('edit-form'));
    $this->assertElementNotPresent('.field--name-field-user-title');
    $this->assertSession()->fieldDisabled('edit-mail');
  }
}
