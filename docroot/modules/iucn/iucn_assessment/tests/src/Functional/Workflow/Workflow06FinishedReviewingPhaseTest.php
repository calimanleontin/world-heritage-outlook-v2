<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Feedback from all reviewers received (assessment_finished_reviewing)
 *
 * @group edw
 * @group edwBrowser
 * @group assessmentWorkflow
 */
class Workflow06FinishedReviewingPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_FINISHED_REVIEWING;

  public function testFinishedReviewingPhaseAccess() {
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Feedback from all reviewers received');
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_COMPARISON]);
  }
}
