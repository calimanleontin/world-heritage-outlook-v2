<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Reference standardisation (assessment_reviewing_references)
 *
 * @group edw
 * @group edwBrowser
 * @group assessmentWorkflow
 */
class Workflow08ReviewingReferencesPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_REVIEWING_REFERENCES;

  public function testReviewingReferencesPhaseAccess() {
    /** @var \Drupal\user\Entity\User $reviewer1 */
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    /** @var \Drupal\user\Entity\User $reviewer2 */
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
    /** @var \Drupal\user\Entity\Use $referencesReviewer1 */
    $referencesReviewer1 = user_load_by_mail(TestSupport::REFERENCES_REVIEWER1);
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_READY_FOR_REVIEW, []);
    $this->editUrl = $assessment->toUrl('edit-form');
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $this->userLogIn(TestSupport::COORDINATOR1);
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
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
    $this->click("[value=\"{$label}\"]");

    $this->userLogIn(TestSupport::REVIEWER2);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node_revision.state_change', [
      'node' => $reviewer2Revision->id(),
      'node_revision' => $reviewer2Revision->getRevisionId(),
    ]);
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINISHED_REVIEWING]);
    $this->click("[value=\"{$label}\"]");
    drupal_flush_all_caches();
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_COMPARISON]);
    $this->drupalPostForm($this->stateChangeUrl, ['field_references_reviewer' => $referencesReviewer1->id()], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_REVIEWING_REFERENCES]);

    $this->editUrl = $assessment->toUrl('edit-form');
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Reference standardisation');
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
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::REFERENCES_REVIEWER1);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINAL_CHANGES]);
  }

  public function testCheckReadOnlyAccess() {
    $this->userLogIn(TestSupport::REFERENCES_REVIEWER1);
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_REVIEWING_REFERENCES, []);
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'values']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'threats']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'protection-management']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'assessing-values']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'conservation-outlook']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'benefits']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  =>'projects']]));
    $this->checkReadOnlyAccess();
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => 'references']]));
    $this->checkNoReadOnlyAccess();
  }

}
