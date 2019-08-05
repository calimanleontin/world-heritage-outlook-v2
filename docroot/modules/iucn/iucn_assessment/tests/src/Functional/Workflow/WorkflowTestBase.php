<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;

class WorkflowTestBase extends IucnAssessmentTestBase {

  const TRANSITION_LABELS = [
    AssessmentWorkflow::STATUS_NEW => 'Save',
    AssessmentWorkflow::STATUS_UNDER_EVALUATION => 'Initiate assessment',
    AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => 'Send to assessor',
    AssessmentWorkflow::STATUS_READY_FOR_REVIEW => 'Submit assessment',
    AssessmentWorkflow::STATUS_UNDER_REVIEW => 'Send assessment to reviewers',
    AssessmentWorkflow::STATUS_FINISHED_REVIEWING => 'Finish reviewing',
    AssessmentWorkflow::STATUS_UNDER_COMPARISON => 'Start comparing reviews',
    AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => 'Send references to reviewer',
    AssessmentWorkflow::STATUS_FINAL_CHANGES => 'Submit',
    AssessmentWorkflow::STATUS_APPROVED => 'Approve',
    AssessmentWorkflow::STATUS_PUBLISHED => 'Publish',
    AssessmentWorkflow::STATUS_DRAFT => 'Start working on a draft',
  ];

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_NEW;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $assessment;

  /**
   * @var \Drupal\Core\Url
   */
  protected $editUrl;

  /**
   * @var \Drupal\Core\Url
   */
  protected $stateChangeUrl;

  public function checkUserAccess(Url $url, $user, $expectedResponseCode) {
    $this->userLogIn($user);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals($expectedResponseCode);
  }

  public function setUp() {
    parent::setUp();
    $this->assessment = $this->createMockAssessmentNode(static::WORKFLOW_STATE);
    $this->editUrl = $this->assessment->toUrl('edit-form');
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $this->assessment->id()]);
  }

  public function checkReadOnlyAccess($assessment, $tab) {
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => $tab]]));
    $this->assertNoLinkByHref('/node/edit_paragraph');
    $this->assertNoLinkByHref('/node/delete_paragraph');
    $this->assertNoLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseNotContains('tabledrag-handle');
  }

  public function checkNoReadOnlyAccess($assessment, $tab) {
    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab'  => $tab]]));
    $this->assertLinkByHref('/node/edit_paragraph');
    $this->assertLinkByHref('/node/delete_paragraph');
    $this->assertLinkByHref('/node/add_paragraph');
    $this->assertSession()->responseContains('tabledrag-handle');
  }


}
