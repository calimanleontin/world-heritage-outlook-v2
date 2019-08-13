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
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'values']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'threats']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'protection-management']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'assessing-values']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'conservation-outlook']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'benefits']]));
    $this->checkReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  =>'projects']]));
    $this->checkNoReadOnlyAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'references']]));
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINAL_CHANGES]);
  }

}
