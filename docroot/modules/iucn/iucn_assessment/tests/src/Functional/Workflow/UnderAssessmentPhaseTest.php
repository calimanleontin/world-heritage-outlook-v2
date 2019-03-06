<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group iucn_assessment_workflow
 */
class UnderAssessmentPhaseTest extends WorkflowTestBase {

  public function testUnderAssessmentPhaseAccess() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();
    $editUrl = $assessment->toUrl('edit-form');
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
    $this->drupalPostForm($stateChangeUrl, ['field_assessor' => $assessor->id()], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertText('Current workflow state: Under assessment');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);

    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);
  }
}
