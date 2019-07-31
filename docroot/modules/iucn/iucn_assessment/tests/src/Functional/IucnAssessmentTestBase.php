<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Assessment Tests.
 */
abstract class IucnAssessmentTestBase extends BrowserTestBase {

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

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
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    ViewTestData::createTestViews(self::class, ['iucn_who_structure']);
    TestSupport::createTestData();
  }

  /**
   * Helper function used to force an assessment state.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param string $newState
   *   The new state.
   * @param array $values
   *   An array of field changes.
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function setAssessmentState(NodeInterface $assessment, $newState, array $values = []) {
    foreach ($values as $field => $value) {
      $assessment->set($field, $value);
    }
    $state = $assessment->field_state->value;
    try {
      return $this->workflowService->createRevision($assessment, $newState, NULL, "{$state} ({$assessment->getRevisionId()}) => {$newState}", TRUE);
    }
    catch (EntityStorageException $e) {
      return NULL;
    }
  }

  /**
   * Creates a new assessment in the provided state.
   *
   * @param $state
   *  Assessment workflow state.
   * @param array $values
   *  Extra fields values.
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function createMockAssessmentNode($state, array $values = []) {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    try {
      $assessment->save();
      return $this->setAssessmentState($assessment, $state, $values);
    }
    catch (EntityStorageException $e) {
      return NULL;
    }
  }

  /**
   * Helper function to log in as an user.
   *
   * @param string $mail
   *   The user mail.
   */
  protected function userLogIn($mail) {
    $user = user_load_by_mail($mail);
    $user->passRaw = 'password';
    $this->drupalLogin($user);
  }

}
