<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowConfigTransition;

/**
 * Defines test scenarios for the assessment workflow.
 *
 * @package Drupal\iucn_assessment\Tests
 * @group iucn
 */
class WorkflowTest extends IucnAssessmentTestBase {

  /**
   * Test the assessment workflow, going through all the states.
   */
  public function testAssessmentWorkflowAccess() {
    $assessment = TestSupport::getNodeByTitle(TestSupport::ASSESSMENT1);

    $this->checkAccessOnEveryState($assessment);
  }

  /**
   * Tests an user access on an assessment edit page.
   *
   * If the vid parameter is passed,
   * the test will be done on the revision edit page.
   *
   * @param string $mail
   *   The user mail.
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param int $assert_response_code
   *   The response code that the visited page should return.
   * @param int $vid
   *   The revision id.
   */
  protected function assertUserAccessOnAssessmentEdit($mail, NodeInterface $assessment, $assert_response_code, $vid = NULL) {
    if (empty($vid)) {
      $url = $assessment->toUrl('edit-form');
    }
    else {
      $url = Url::fromRoute('node.revision_edit', ['node' => $assessment->id(), 'node_revision' => $vid]);
    }
    $this->userLogIn($mail);
    $this->drupalGet($url);
    $this->assertResponse($assert_response_code);
  }

  /**
   * Check all users' access on an assessment edit page.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   */
  protected function assertAllUserAccessOnAssessmentEdit(NodeInterface $assessment) {
    // Administrators and manages can edit any assessment, regardless of state.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::ADMINISTRATOR, $assessment, 200);
    $this->assertUserAccessOnAssessmentEdit(TestSupport::IUCN_MANAGER, $assessment, 200);

    $state = $assessment->field_state->value;

    // Coordinators cannot edit assessments in under_assessment or published.
    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT) {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 403);
    }
    else {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 200);
    }

    // Assessor 1 should only be able to edit in the under_assessment state.
    if ($state == 'assessment_under_assessment') {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 200);
    }
    else {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 403);
      if ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
        $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 403);
      }
      else {
        $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 200);
      }
    }

    // Coordinator 2 can only edit the assessment when it has no coordinator.
    if ($state == 'assessment_new' || $state == 'assessment_creation') {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR2, $assessment, 200);
    }
    else {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR2, $assessment, 403);
    }

    // Assessor 2 is never allowed to edit this assessment.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR2, $assessment, 403);
    // The reviewers are never allowed to edit assessments, only revisions.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::REVIEWER1, $assessment, 403);
    $this->assertUserAccessOnAssessmentEdit(TestSupport::REVIEWER2, $assessment, 403);
  }

  /**
   * Loop an assessment through all the states and check all users' edit access.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   */
  protected function checkAccessOnEveryState(NodeInterface $assessment) {
    $states = [
      AssessmentWorkflow::STATUS_NEW,
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_REVIEW,
      AssessmentWorkflow::STATUS_FINISHED_REVIEWING,
      AssessmentWorkflow::STATUS_UNDER_COMPARISON,
      AssessmentWorkflow::STATUS_APPROVED,
      AssessmentWorkflow::STATUS_PUBLISHED,
    ];
    foreach ($states as $state) {
      $field_changes = NULL;
      if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT) {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::ASSESSOR1);
        $field_changes = ['field_assessor' => $user->id()];
      }
      elseif ($state == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::REVIEWER1);
        $field_changes = ['field_reviewers' => $user->id()];
      }
      $this->setAssessmentState($assessment, $state, $field_changes);
      $this->assertEqual($assessment->field_state->value, $state, "Testing state: $state");
      $this->assertAllUserAccessOnAssessmentEdit($assessment);
    }
  }

  /**
   * Make sure transition conditions are respected.
   *
   * Moving to state under_evaluation requires a coordinator.
   * Moving to state under_assessment requires an assessor.
   * Moving to state under_review requires a reviewer.
   */
  protected function testValidTransitions() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $this->setAssessmentState($assessment, 'assessment_new');
    $this->userLogIn(TestSupport::COORDINATOR1);
    $url = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $this->drupalGet($url);
    $this->assertText(t('Coordinator'));
    $this->assertNoText(t('Assessor'));
    $this->assertNoText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_new_under_evaluation');
    $button_label = $transition->label();
    // Try to change the state without assigning coordinator.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_new');

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $coordinator1 = user_load_by_mail(TestSupport::COORDINATOR1);
    $this->drupalPostForm($url, ['field_coordinator' => $coordinator1->id()], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_under_evaluation');

    $this->drupalGet($url);
    $this->assertNoText(t('Coordinator'));
    $this->assertText(t('Assessor'));
    $this->assertNoText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_under_evaluation_under_assessment');
    $button_label = $transition->label();
    // Try to change the state without assigning assessor.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_under_evaluation');

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $assessor1 = user_load_by_mail(TestSupport::ASSESSOR1);
    $this->drupalPostForm($url, ['field_assessor' => $assessor1->id()], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_under_assessment');

    $this->userLogIn(TestSupport::ASSESSOR1);
    $transition = WorkflowConfigTransition::load('assessment_under_assessment_ready_for_review');
    $button_label = $transition->label();
    $this->drupalPostForm($url, [], t($button_label));
    drupal_flush_all_caches();

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalGet($url);
    $this->assertNoText(t('Coordinator'));
    $this->assertNoText(t('Assessor'));
    $this->assertText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_ready_for_review_under_review');
    $button_label = $transition->label();
    // Try to change the state without assigning reviewer.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_ready_for_review');

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $this->drupalPostForm($url, ['field_reviewers[]' => [$reviewer1->id()]], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, 'assessment_under_review');
  }

}
