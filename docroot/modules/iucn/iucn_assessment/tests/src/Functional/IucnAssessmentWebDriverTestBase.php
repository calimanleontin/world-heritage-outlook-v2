<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Assessment Tests using Web drivers.
 */
abstract class IucnAssessmentWebDriverTestBase extends WebDriverTestBase {

  use IucnAssessmentTestTrait;

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

}
