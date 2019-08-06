<?php
namespace Drupal\Tests\iucn_assessment\Functional\Workflow;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
/**
 * Phase: Under review (assessment_under_review)
 *
 * @group iucn_assessment_workflow
 */
class Workflow05UnderReviewPhaseTest extends WorkflowTestBase {
  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_READY_FOR_REVIEW;
  public function testUnderReviewPhaseAccess() {
    /** @var \Drupal\user\UserInterface $reviewer1 */
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    /** @var \Drupal\user\UserInterface $reviewer2 */
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
    /** @var \Drupal\user\UserInterface $reviewer3 */
    $reviewer3 = user_load_by_mail(TestSupport::REVIEWER3);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($this->stateChangeUrl, [
      'field_reviewers[]' => [
        $reviewer1->id(),
        $reviewer2->id(),
      ],
    ], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_REVIEW]);
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Under review');
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
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
    $reviewer1Revision = $this->workflowService->getReviewerRevision($this->assessment, $reviewer1->id());
    $this->editUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($this->assessment->getRevisionId(), $reviewer1Revision->getRevisionId(), 'A new revision was created for Reviewer1.');
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $reviewer2Revision = $this->workflowService->getReviewerRevision($this->assessment, $reviewer2->id());
    $this->editUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($this->assessment->getRevisionId(), $reviewer2Revision->getRevisionId(), 'A new revision was created for Reviewer2.');
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER2, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER2, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER3, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    // Remove reviewer2 and add reviewer 3.
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($this->stateChangeUrl, [
      'field_reviewers[]' => [
        $reviewer1->id(),
        $reviewer3->id(),
      ],
    ], 'Save');
    $reviewer2Revision = $this->entityTypeManager->getStorage('node')->loadRevision($reviewer2Revision->getRevisionId());
    $this->assertEquals(AssessmentWorkflow::STATUS_FINISHED_REVIEWING, $reviewer2Revision->field_state->value, 'Revision for reviewer 2 was marked as finished.');
    $reviewer3Revision = $this->workflowService->getReviewerRevision($this->assessment, $reviewer3->id());
    $this->editUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer3Revision->id(),
      'node_revision' => $reviewer3Revision->getRevisionId(),
    ]);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer3Revision->id(),
      'node_revision' => $reviewer3Revision->getRevisionId(),
    ]);
    $this->assertNotEqual($this->assessment->getRevisionId(), $reviewer3Revision->getRevisionId(), 'A new revision was created for Reviewer3.');
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER3, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER3, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->userLogIn(TestSupport::REVIEWER1);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
    $this->userLogIn(TestSupport::REVIEWER3);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
  }
  public function testReadOnlyAccessFoReviewers(){
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_REVIEW, []);
    $reviewer1Revision = $this->workflowService->createRevision($assessment,AssessmentWorkflow::STATUS_UNDER_REVIEW, $reviewer1->id() );
    $this->editUrl = Url::fromRoute('node.revision_edit', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $this->userLogIn(TestSupport::REVIEWER1);
    $assessment = Node::load($assessment->id());
    $this->drupalGet($this->editUrl);
    $this->assertNoLinkByHref('/node/edit_paragraph');
    $this->assertNoLinkByHref('/node/delete_paragraph');
    $this->assertNoLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseNotContains('tabledrag-handle');
  }
  public function testStateAssessmentChangeToFinishedReviewing() {
    /** @var \Drupal\user\Entity\User $reviewer1 */
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    /** @var \Drupal\user\Entity\User $reviewer2 */
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_READY_FOR_REVIEW, []);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_REVIEW]);
    $this->click("[value=\"{$label}\"]");
    $reviewer1Revision = $this->workflowService->getReviewerRevision($assessment,$reviewer1->id());
    $reviewer2Revision = $this->workflowService->getReviewerRevision($assessment,$reviewer2->id());
    $this->userLogIn(TestSupport::REVIEWER1);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer1Revision->id(),
      'node_revision' => $reviewer1Revision->getRevisionId(),
    ]);
    $assessment = Node::load($assessment->id());
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
    $this->click("[value=\"{$label}\"]");
    $this->userLogIn(TestSupport::REVIEWER2);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $assessment = Node::load($assessment->id());
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
    $this->click("[value=\"{$label}\"]");
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEquals(AssessmentWorkflow::STATUS_FINISHED_REVIEWING, $assessment->field_state->value);
  }
}