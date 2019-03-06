<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Includes following phases:
 *  - under comparison
 *  - reviewing references
 *  - approved
 *  - published
 *  - draft
 *
 * @group iucn_assessment_workflow
 */
class FinalPhasesTest extends WorkflowTestBase {

  public function testFinalPhasesAccess() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();
    $editUrl = $assessment->toUrl('edit-form');
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
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

    $reviewer1Revision = $this->workflowService->getReviewerRevision($assessment, $reviewer1->id());
    $reviewer1RevisionEditUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $reviewer1RevisionStateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $reviewer2Revision = $this->workflowService->getReviewerRevision($assessment, $reviewer2->id());
    $reviewer2RevisionEditUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $reviewer2RevisionStateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);

    $this->userLogIn(TestSupport::REVIEWER1);
    $this->drupalPostForm($reviewer1RevisionStateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);

    $this->userLogIn(TestSupport::REVIEWER2);
    $this->drupalPostForm($reviewer2RevisionStateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_COMPARISON]);

    $this->checkUserAccess($reviewer1RevisionEditUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer1RevisionStateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($reviewer2RevisionEditUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($reviewer2RevisionStateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Under comparison');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_REVIEWING_REFERENCES]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Reviewing references');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_APPROVED]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Approved');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->userLogIn(TestSupport::IUCN_MANAGER);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_PUBLISHED]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $redirected = $this->getUrl() == $stateChangeUrl->toString();
    $this->assertTrue($redirected, 'The user was redirected to the state change page.');
    $this->assertSession()->pageTextContains('Current workflow state: Published');

    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $redirected = $this->getUrl() == $stateChangeUrl->toString();
    $this->assertTrue($redirected, 'The user was redirected to the state change page.');
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->userLogIn(TestSupport::IUCN_MANAGER);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_DRAFT]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Draft');
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER2, 403);

    $this->userLogIn(TestSupport::IUCN_MANAGER);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_PUBLISHED]);
  }
}
