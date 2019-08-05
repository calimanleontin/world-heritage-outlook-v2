<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Being assessed (assessment_under_assessment)
 *
 * @group iucn_assessment_workflow
 */
class Workflow03UnderAssessmentPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_UNDER_ASSESSMENT;

  public function testUnderAssessmentPhaseAccess() {
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Being assessed');
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);
  }

  public function testReadOnlyAccessForAssessor() {
    $this->userLogIn(TestSupport::ASSESSOR1);
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, []);
    $this->drupalGet($assessment->toUrl('edit-form'));

    sleep(20);
    $this->assertNoLinkByHref('/node/edit_paragraph');
    $this->assertNoLinkByHref('/node/delete_paragraph');
    $this->assertNoLinkByHref('/node/add_paragraph');
    $this->assertNoLinkByHref('#');
//    $this->assertLinkByHref('#');
  }
}
