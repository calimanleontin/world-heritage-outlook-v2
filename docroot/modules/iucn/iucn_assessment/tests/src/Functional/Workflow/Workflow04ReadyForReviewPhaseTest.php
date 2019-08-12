<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Pre-review edits (assessment_ready_for_review)
 *
 * @group iucn_assessment_workflow
 * @group edw_tests
 */
class Workflow04ReadyForReviewPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_READY_FOR_REVIEW;

  public function testReadyForReviewPhaseAccess() {
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Pre-review edits');
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($this->stateChangeUrl, [
      'field_reviewers[]' => [
        $reviewer1->id(),
        $reviewer2->id(),
      ],
    ], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_REVIEW]);
  }

}
