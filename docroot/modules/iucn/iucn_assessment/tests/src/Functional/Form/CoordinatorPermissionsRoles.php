<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

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
    $this->assertElementNotPresent(".fieldgroup .form-composite .js-form-item .form-item .js-form-wrapper .form-wrapper");
  }

//  public function testNotAllowedAssignRoles() {
//    $this->userLogIn(TestSupport::COORDINATOR1);
//
//    $user = \Drupal::entityTypeManager()
//      ->getStorage('user')
//      ->loadByProperties(
//        [
//          'mail' => TestSupport::COORDINATOR1,
//        ]
//      );
//
//    $user = reset($user);
//    $this->drupalGet($user->url('assign-form'));
//  }
}
