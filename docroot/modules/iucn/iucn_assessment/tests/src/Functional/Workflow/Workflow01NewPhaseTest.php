<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: New (assessment_creation, assessment_new)
 *
 * @group iucn_assessment_workflow
 * @group edw_tests
 */
class Workflow01NewPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_NEW;

  public function testNewPhaseAccess() {
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: New');
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
  }

}
