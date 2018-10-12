<?php

namespace Drupal\iucn_assessment\Tests;
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

}
