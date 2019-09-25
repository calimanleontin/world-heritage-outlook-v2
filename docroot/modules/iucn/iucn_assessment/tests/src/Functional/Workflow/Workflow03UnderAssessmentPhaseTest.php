<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Being assessed (assessment_under_assessment)
 *
 * @group edw
 * @group edwBrowser
 * @group assessmentWorkflow
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
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 200);
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
    $this->drupalGet($this->editUrl);
    $this->checkReadOnlyAccess();
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);
  }

  public function testChangingAssessorWhenUnderAssessment() {
    /** @var \Drupal\user\Entity\User $assessor1 */
    $assessor1 = user_load_by_mail(TestSupport::ASSESSOR1);
    /** @var \Drupal\user\Entity\User $assessor2 */
    $assessor2 = user_load_by_mail(TestSupport::ASSESSOR2);

    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $assessment->setTitle('Random title');
    $assessment->save();

    $this->drupalGet($stateChangeForm);
    $this->assertSession()->pageTextContains('Being assessed');
    $this->drupalPostForm($stateChangeForm, ['field_assessor' => $assessor2->id()], 'Save');
    $this->assertSession()->pageTextContains('was successfully updated.');

    // Assessor1's revision should have been deleted.
    // Check that there is no revision with its assessor set to ASSESSOR1 or with the RANDOM TITLE title
    $revisionIds = $this->entityTypeManager->getStorage('node')->revisionIds($assessment);
    $revisionDeleted = TRUE;
    foreach ($revisionIds as $rid) {
      $nodeRevision = $this->workflowService->getAssessmentRevision($rid);
      if ($nodeRevision->field_state->value == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT
        && ($nodeRevision->getTitle() == 'Random title' || $nodeRevision->field_assessor->target_id == $assessor1->id())) {
        $revisionDeleted = FALSE;
      }
    }
    $this->assertTrue($revisionDeleted, 'Old assessor revision successfully deleted');

    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);
    $assessment = Node::load($assessment->id());
    // Set the assessment title.
    $assessment->setTitle('Random title 2');
    $assessment->save();
    $this->drupalGet($assessment->toUrl('edit-form'));
    $this->userLogIn(TestSupport::COORDINATOR1);
    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    // Force finish the assessment.
    $this->drupalPostForm($stateChangeForm, [], 'Force finish assessment');

    // Make sure the assessment is now ready for review, and the assessor's change is kept.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->getTitle(), 'Random title 2');
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);
  }

}
