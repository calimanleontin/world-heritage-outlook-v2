<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Under review (assessment_under_review)
 *
 * @group iucn_assessment_workflow
 */
class Workflow05JsUnderReviewPhaseTest extends IucnAssessmentWebDriverTestBase {
  public function testStateAssessmentChangeToFinishedReviewing() {
    // Coordinator forced finish review
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_READY_FOR_REVIEW, []);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($this->stateChangeUrl);
    $label = t(WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_REVIEW]);
    $this->click("[value=\"{$label}\"]");
    $assessment = Node::load($assessment->id());
    $this->drupalPostForm($this->stateChangeUrl, [], t('Force finish reviewing'));
    $driver = $this->getSession()->getDriver();
    $driver->getWebDriverSession()->accept_alert();
    $this->drupalGet($this->stateChangeUrl);
    $this->assertEquals(AssessmentWorkflow::STATUS_FINISHED_REVIEWING, $assessment->field_state->value);
  }
}