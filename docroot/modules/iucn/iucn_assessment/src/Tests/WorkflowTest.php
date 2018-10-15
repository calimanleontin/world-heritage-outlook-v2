<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Defines test scenarios for the assessment workflow.
 *
 * @package Drupal\iucn_assessment\Tests
 */
class WorkflowTest extends IucnAssessmentTestBase {

  /**
   * Test the assessment workflow, going through all the states.
   */
  public function testAssessmentWorkflow() {
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
    $user = user_load_by_mail($mail);
    $user->pass_raw = 'password';
    $this->drupalLogin($user);
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
    if ($state == 'assessment_under_assessment' || $state == 'assessment_published') {
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
      'assessment_creation',
      'assessment_new',
      'assessment_under_evaluation',
      'assessment_under_assessment',
      'assessment_ready_for_review',
      'assessment_under_review',
      'assessment_finished_reviewing',
      'assessment_under_comparison',
      'assessment_approved',
      'assessment_published',
    ];
    foreach ($states as $state) {
      $field_changes = NULL;
      if ($state == 'assessment_under_evaluation') {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::COORDINATOR1);
        $field_changes = ['field_coordinator' => $user->id()];
      }
      if ($state == 'assessment_under_assessment') {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::ASSESSOR1);
        $field_changes = ['field_assessor' => $user->id()];
      }
      elseif ($state == 'assessment_under_review') {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::REVIEWER1);
        $field_changes = ['field_reviewers' => $user->id()];
      }
      $this->setAssessmentState($assessment, $state, $field_changes);
      $this->assertEqual($assessment->field_state->value, $state, "Testing state: $state");
      $this->assertAllUserAccessOnAssessmentEdit($assessment);
    }


  }

}
