<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group iucn_assessment_workflow
 */
class Workflow05UnderReviewPhaseTest extends WorkflowTestBase {

  public function testUnderReviewPhaseAccess() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();
    $editUrl = $assessment->toUrl('edit-form');
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
    $reviewer3 = user_load_by_mail(TestSupport::REVIEWER3);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
    $this->drupalPostForm($stateChangeUrl, ['field_assessor' => $assessor->id()], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);
    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [
      'field_reviewers[]' => [
        $reviewer1->id(),
        $reviewer2->id(),
      ],
    ], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_REVIEW]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Under review');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER3, 403);

    $reviewer1Revision = $this->workflowService->getReviewerRevision($assessment, $reviewer1->id());
    $reviewer1RevisionEditUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $reviewer1RevisionStateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($assessment->getRevisionId(), $reviewer1Revision->getRevisionId(), 'A new revision was created for Reviewer1.');
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::REVIEWER1, 200);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::REVIEWER1, 200);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::REVIEWER3, 403);

    $reviewer2Revision = $this->workflowService->getReviewerRevision($assessment, $reviewer2->id());
    $reviewer2RevisionEditUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $reviewer2RevisionStateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($assessment->getRevisionId(), $reviewer2Revision->getRevisionId(), 'A new revision was created for Reviewer2.');
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::REVIEWER2, 200);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::REVIEWER2, 200);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::REVIEWER3, 403);

    // Remove reviewer2 and add reviewer 3.
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [
      'field_reviewers[]' => [
        $reviewer1->id(),
        $reviewer3->id(),
      ],
    ], 'Save');
    $reviewer2Revision = $this->entityTypeManager->getStorage('node')->loadRevision($reviewer2Revision->getRevisionId());
    $this->assertEquals(AssessmentWorkflow::STATUS_FINISHED_REVIEWING, $reviewer2Revision->field_state->value, 'Revision for reviewer 2 was marked as finished.');

    $reviewer3Revision = $this->workflowService->getReviewerRevision($assessment, $reviewer3->id());
    $reviewer3RevisionEditUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer3Revision->id(),
      'node_revision' => $reviewer3Revision->getRevisionId(),
    ]);
    $reviewer3RevisionStateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer3Revision->id(),
      'node_revision' => $reviewer3Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($assessment->getRevisionId(), $reviewer3Revision->getRevisionId(), 'A new revision was created for Reviewer3.');
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($reviewer3RevisionEditUrl, TestSupport::REVIEWER3, 200);
    $this->checkUserAccess($reviewer3RevisionStateChangeUrl, TestSupport::REVIEWER3, 200);

    $this->userLogIn(TestSupport::REVIEWER1);
    $this->drupalPostForm($reviewer1RevisionStateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);

    $this->userLogIn(TestSupport::REVIEWER3);
    $this->drupalPostForm($reviewer3RevisionStateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
  }
}
