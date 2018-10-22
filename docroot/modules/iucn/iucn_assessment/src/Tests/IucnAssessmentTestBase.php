<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\node\NodeInterface;
use Drupal\simpletest\WebTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Assessment Tests.
 */
abstract class IucnAssessmentTestBase extends WebTestBase {

  public static $modules = [
    'iucn_who_structure',
  ];

  public static $testViews = [
    'users_by_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->strictConfigSchema = FALSE;
    parent::setUp();
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();
    ViewTestData::createTestViews(self::class, ['iucn_who_structure']);
    TestSupport::createAllTestData();
  }

  /**
   * Helper function used to force an assessment state.
   *
   * If field_changes is passed, for every key in the array
   * we will set $node->{$key}->target_id = $value.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param string $state
   *   The state.
   * @param array $field_changes
   *   An array of field changes.
   */
  protected function setAssessmentState(NodeInterface $node, $state, $field_changes = NULL) {
    // We need to log in as an administrator because
    // it is the only role capable of executing every transition.
    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $node->field_state->value = $state;
    if (!empty($field_changes)) {
      foreach ($field_changes as $field => $target_id) {
        $node->{$field}->target_id = $target_id;
      }
    }
    $node->save();
  }

  /**
   * Helper function to log in as an user.
   *
   * @param string $mail
   *   The user mail.
   */
  protected function userLogIn($mail) {
    $user = user_load_by_mail($mail);
    $user->pass_raw = 'password';
    $this->drupalLogin($user);
  }

}
