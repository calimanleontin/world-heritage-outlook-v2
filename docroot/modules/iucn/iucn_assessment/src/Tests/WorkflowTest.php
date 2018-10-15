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
    // Fresh assessment with state = NEW and no coordinator.
    $assessment = TestSupport::getNodeByTitle(TestSupport::ASSESSMENT1);

    $this->assertAllUserAccessOnAssessmentEdit($assessment);

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

    if ($state == 'assessment_under_assessment') {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 403);
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR2, $assessment, 403);
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 200);
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR2, $assessment, 403);
    }
    else {
      if ($state == 'assessment_published') {
        $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 403);
      }
      else {
        $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 200);
      }
    }
    $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR2, $assessment, 403);
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
      if ($state == 'assessment_under_assessment') {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::ASSESSOR1);
        $field_changes = ['field_assessor' => $user->id()];
      }
      elseif ($state == 'assessment_under_review') {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::REVIEWER1);
        $field_changes = ['field_assessor' => $user->id()];
      }
      $this->setAssessmentState($assessment, $state, $field_changes);
    }

    $this->assertAllUserAccessOnAssessmentEdit($assessment);

  }

}
