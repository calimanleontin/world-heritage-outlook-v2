<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Web Driver Assessment Tests.
 */
abstract class IucnAssessmentWebDriverTestBase extends WebDriverTestBase {

  use AssessmentTestTrait;

  /**
   * Disable strict config schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

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
    parent::setUp();
    $this->workflowService = $this->container->get('iucn_assessment.workflow');
    ViewTestData::createTestViews(self::class, ['iucn_who_structure']);
    TestSupport::createTestData();
  }

}
