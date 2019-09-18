<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;

class WorkflowTestBase extends IucnAssessmentTestBase {

  const TRANSITION_LABELS = [
    AssessmentWorkflow::STATUS_NEW => 'Save',
    AssessmentWorkflow::STATUS_UNDER_EVALUATION => 'Initiate assessment',
    AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => 'Send to assessor',
    AssessmentWorkflow::STATUS_READY_FOR_REVIEW => 'Submit assessment',
    AssessmentWorkflow::STATUS_UNDER_REVIEW => 'Send assessment to reviewers',
    AssessmentWorkflow::STATUS_FINISHED_REVIEWING => 'Submit review',
    AssessmentWorkflow::STATUS_UNDER_COMPARISON => 'Start comparing reviews',
    AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => 'Send to reference reviewer',
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

  public function setUp() {
    parent::setUp();
    $this->assessment = $this->createMockAssessmentNode(static::WORKFLOW_STATE);
    $this->editUrl = $this->assessment->toUrl('edit-form');
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $this->assessment->id()]);
  }

  public function checkUserAccess(Url $url, $user, $expectedResponseCode) {
    $this->userLogIn($user);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals($expectedResponseCode);
  }
}
