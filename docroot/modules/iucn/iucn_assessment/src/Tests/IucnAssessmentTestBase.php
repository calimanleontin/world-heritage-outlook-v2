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

  protected function setAssessmentState(NodeInterface $node, $state) {
    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $node->field_state->value = $state;
    $node->save();
  }

  protected function userLogIn($mail) {
    $user = user_load_by_mail($mail);
    $user->pass_raw = 'password';
    $this->drupalLogin($user);
  }

}
