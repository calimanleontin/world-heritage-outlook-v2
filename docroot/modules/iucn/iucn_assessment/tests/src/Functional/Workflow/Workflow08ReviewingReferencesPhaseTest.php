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
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form'));
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'threats']]));
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'protection-management']]));
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'assessing-values']]));
//    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'conservation-outlook']]));
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'benefits']]));
    $this->checkReferencesReviewerParagraphEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'projects']]));
    $this->checkReferencesReviewerReferencesEditAccess($this->assessment->toUrl('edit-form', ['query' => ['tab'  => 'references']]));
    $this->drupalPostForm($this->stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_FINAL_CHANGES]);
  }
  
  protected function checkReferencesReviewerParagraphEditAccess(Url $url) {
    if (!empty($url)) {
      $this->drupalGet($url);
    }
    $this->assertLinkByHref('/node/edit_paragraph');
    // @TODO: uncomment this when #7330 is done.
//    $this->assertNoLinkByHref('/node/delete_paragraph');
//    $this->assertNoLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseNotContains('field-multiple-drag');
  }

  protected function checkReferencesReviewerReferencesEditAccess(Url $url) {
    if (!empty($url)) {
      $this->drupalGet($url);
    }
    $this->assertLinkByHref('/node/edit_paragraph');
    $this->assertLinkByHref('/node/delete_paragraph');
    $this->assertLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseContains('field-multiple-drag');
  }

}
