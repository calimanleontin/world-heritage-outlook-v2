<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\iucn_assessment\Tests\Workflow\WorkflowTestBase;

/**
 * @group iucn
 */
class ChangeStateFormTest extends WorkflowTestBase {

  //../bin/run-test.sh --class Drupal\\iucn_assessment\\Tests\\ChangeStateFormTest::testAccess

  public function testAccess() {
    //::testAccess
    //+ Create an assessment
    //+ move it through each state.

    //- After each transition, test the access for all roles.

    // (Test
    //    coordinators which were not assigned to the assessment,
    //    coordinator which was assiged,
    // same for assessors, etc).

    // To change the state of the assessment submit the form using the user which has access to it:
    // $this->drupalPostForm($stateChangeUrl, [], 'Finish assessment');

    $assessment = TestSupport::createAssessment();
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $coordinator1 = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor1 = user_load_by_mail(TestSupport::ASSESSOR1);
    $coordinator2 = user_load_by_mail(TestSupport::COORDINATOR2);
    $assessor2 = user_load_by_mail(TestSupport::ASSESSOR2);

    foreach (self::TRANSITION_LABELS as $assessmentWorkflowStatus => $transitionLabel) {
      $edit = [];
      switch ($assessmentWorkflowStatus) {
        case AssessmentWorkflow::STATUS_UNDER_EVALUATION:
          // 'Initiate assessment'.
          $edit = ['field_coordinator' => $coordinator1->id()];
          break;

        case AssessmentWorkflow::STATUS_UNDER_ASSESSMENT:
          // 'Send to assessor'.
          $edit = ['field_assessor' => $assessor1->id()];
          break;

      }
      $submit = self::TRANSITION_LABELS[$assessmentWorkflowStatus];
      $this->drupalPostForm($stateChangeUrl, $edit, $submit);
    }
  }

  //Tests:
  //::testValidation
  //   - see #5705 - test that the validation fails for all possible cases - also see NodeSiteAssessmentStateChangeForm::validateNode
  //https://helpdesk.eaudeweb.ro/issues/5705
  //Add validation to "State change" form
  public function testValidation() {

  }
}
