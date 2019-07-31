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

  public function testUnderAssessmentPhaseAccess() {
    /** @var \Drupal\user\UserInterface $coordinator */
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    /** @var \Drupal\user\UserInterface $assessor */
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $referencedUsers = [
      'field_coordinator' => $coordinator->id(),
      'field_assessor' => $assessor->id(),
    ];
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, $referencedUsers);
    $editUrl = $assessment->toUrl('edit-form');
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Being assessed');
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
    $this->checkUserAccess($editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);
  }
}
