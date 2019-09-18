<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

class UserAgreementTest extends IucnAssessmentTestBase {

  public function testNewUserChangePassword() {
    //Check that a new user can change the password before accept the user agreement
    $userMail = 'test@test.test';
    TestSupport::createUser($userMail, ['coordinator']);
    $this->userLogIn($userMail);

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(
        [
          'mail' => $userMail,
        ]
      );

    $user = reset($user);
    $this->drupalGet($user->url('edit-form'));

    $this->assertSession()->responseNotContains('User Agreement');
  }

}
