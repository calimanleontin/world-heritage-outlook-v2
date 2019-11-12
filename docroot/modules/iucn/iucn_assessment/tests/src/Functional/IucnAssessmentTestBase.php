<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Assessment Tests.
 */
abstract class IucnAssessmentTestBase extends BrowserTestBase {

  use AssessmentTestTrait;

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface */
  protected $entityFieldManager;

  /**
   * Array with all fields rendered on each tab.
   *
   * @var array
   */
  protected $tabs = [
    'values' => [
      'field_as_values_wh',
      'field_as_values_bio',
    ],
    'threats' => [
      'field_as_threats_current',
      'field_as_threats_potential',
      'field_as_threats_current_text',
      'field_as_threats_current_rating',
      'field_as_threats_potent_text',
      'field_as_threats_potent_rating',
      'field_as_threats_text',
      'field_as_threats_rating',
    ],
    'protection-management' => [
      'field_as_protection',
      'field_as_protection_ov_text',
      'field_as_protection_ov_rating',
      'field_as_protection_ov_out_text',
      'field_as_protection_ov_out_rate',
      'field_as_protection_ov_practices',
    ],
    'assessing-values' => [
      'field_as_values_wh',
      'field_as_vass_wh_text',
      'field_as_vass_wh_state',
      'field_as_vass_wh_trend',
      'field_as_vass_bio_text',
      'field_as_vass_bio_state',
      'field_as_vass_bio_trend',
    ],
    'conservation-outlook' => [
      'field_as_global_assessment_text',
      'field_as_global_assessment_level',
    ],
    'benefits' => [
      'field_as_benefits',
      'field_as_benefits_summary',
    ],
    'projects' => [
      'field_as_projects',
    ],
    'references' => [
      'field_as_references_p',
    ],
  ];

  /**
   * Disable strict config schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  public static $modules = [
    'iucn_who_structure',
    'entity_print_test',
    'dblog',
  ];

  public static $testViews = [
    'users_by_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Use test print engine.
    \Drupal::configFactory()
      ->getEditable('entity_print.settings')
      ->set('print_engines.pdf_engine', 'testprintengine')
      ->save();

    module_set_weight('iucn_assessment', 1);
    drupal_flush_all_caches();
    $this->workflowService = $this->container->get('iucn_assessment.workflow');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    ViewTestData::createTestViews(self::class, static::$modules);
    TestSupport::createTestData();
  }

  public function checkReadOnlyAccess(Url $url = NULL) {
    if (!empty($url)) {
      $this->drupalGet($url);
    }
    $this->assertNoLinkByHref('/node/edit_paragraph');
    $this->assertNoLinkByHref('/node/delete_paragraph');
    $this->assertNoLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseNotContains('field-multiple-drag');
  }

  public function checkNoReadOnlyAccess(Url $url = NULL) {
    if (!empty($url)) {
      $this->drupalGet($url);
    }
    $this->assertLinkByHref('/node/edit_paragraph');
    $this->assertLinkByHref('/node/delete_paragraph');
    $this->assertLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseContains('field-multiple-drag');
  }


}
