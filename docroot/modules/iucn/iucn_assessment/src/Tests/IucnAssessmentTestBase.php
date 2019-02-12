<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\node\NodeInterface;
use Drupal\simpletest\WebTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base for Assessment Tests.
 */
abstract class IucnAssessmentTestBase extends WebTestBase {

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface */
  protected $entityDefinitionUpdateManager;

  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface */
  protected $entityFieldManager;

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
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->entityDefinitionUpdateManager = $this->container->get('entity.definition_update_manager');
    $this->entityDefinitionUpdateManager->applyUpdates();
    ViewTestData::createTestViews(self::class, ['iucn_who_structure']);
    TestSupport::createTestData();
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
   *
   * @return \Drupal\node\NodeInterface
   */
  protected function setAssessmentState(NodeInterface $node, $newState, $field_changes = NULL) {
    if (!empty($field_changes)) {
      foreach ($field_changes as $field => $target_id) {
        $node->{$field}->target_id = $target_id;
      }
    }
    $state = $node->field_state->value;
    return $this->workflowService->createRevision($node, $newState, NULL, "{$state} ({$node->getRevisionId()}) => {$newState}", TRUE);
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
