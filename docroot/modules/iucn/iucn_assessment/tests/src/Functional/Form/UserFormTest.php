<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class UserFormTest extends IucnAssessmentTestBase {

  /**
   * A new user can change the password before accepting the user agreement.
   */
  public function testNewUserCanChangePassword() {
    $userMail = 'test@test.test';
    $userId = TestSupport::createUser($userMail, ['assessor'], FALSE);
    $user = User::load($userId);
    $this->userLogIn($userMail);

    $userAgreementTitle = 'User Agreement | ' . \Drupal::config('system.site')->get('name');

    $this->drupalGet(Url::fromRoute('who.user-dashboard'));
    $this->assertTitle($userAgreementTitle);
    $this->assertFieldById('edit-agree', NULL);

    $this->drupalGet($user->toUrl('edit-form'));
    $this->assertNoText($userAgreementTitle);
    $this->assertFieldById('edit-pass-pass1', NULL);
    $this->assertFieldById('edit-pass-pass2', NULL);
  }

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
      'coordinator'
    ];

    $validRoles = [
      'assessor',
      'reviewer',
      'references_reviewer',
    ];

    $allRoles = array_keys(Role::loadMultiple());

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->checkUserCanEditRoles([], $allRoles, TestSupport::IUCN_MANAGER);
    $this->checkUserCanEditRoles([], $allRoles, TestSupport::ADMINISTRATOR);
    $this->checkUserCanEditRoles($validRoles, $invalidRoles, TestSupport::ASSESSOR1);
    $this->checkUserCanEditRoles($validRoles, $invalidRoles, TestSupport::REVIEWER1);
    $this->checkUserCanEditRoles($validRoles, $invalidRoles, TestSupport::REFERENCES_REVIEWER1);
    $this->checkUserCanEditRoles($validRoles, $invalidRoles, TestSupport::COORDINATOR2);

    $this->drupalGet(Url::fromRoute('user.admin_create'));
    $this->checkUserCanEditRoles($validRoles, $invalidRoles);
  }

  /**
   * Check that a given user can edit edit $validRoles
   * but cannot edit $invalidRoles
   *
   * @param $validRoles
   * @param $invalidRoles
   * @param string|null $userEmail
   */
  protected function checkUserCanEditRoles($validRoles, $invalidRoles, $userEmail = NULL) {
    if (!empty($userEmail)) {
      $user = user_load_by_mail($userEmail);
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

  /**
   * Check every role for right to view title and edit email.
   */
  public function testRoleViewTitleEditMail() {
    $allowedMails = [
      TestSupport::COORDINATOR1,
      TestSupport::ADMINISTRATOR,
      TestSupport::IUCN_MANAGER,
    ];

    $notAllowedMails = [
      TestSupport::ASSESSOR1,
      TestSupport::REVIEWER1,
      TestSupport::REFERENCES_REVIEWER1,
    ];

    foreach ($allowedMails + $notAllowedMails as $mail) {
      $this->userLogIn($mail);

      $user = user_load_by_mail($mail);
      $this->drupalGet($user->url('edit-form'));

      if (in_array($mail, $allowedMails)) {
        $this->assertElementPresent('.field--name-field-user-title');
        $this->assertSession()->fieldEnabled('edit-mail');
      }

      if (in_array($mail, $notAllowedMails)) {
        $this->assertElementNotPresent('.field--name-field-user-title');
        $this->assertSession()->fieldDisabled('edit-mail');
      }
    }
  }

}
