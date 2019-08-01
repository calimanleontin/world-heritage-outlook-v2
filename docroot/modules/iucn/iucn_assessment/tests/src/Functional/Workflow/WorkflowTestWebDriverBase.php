<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;

class WorkflowTestWebDriverBase extends IucnAssessmentWebDriverTestBase {

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

}
