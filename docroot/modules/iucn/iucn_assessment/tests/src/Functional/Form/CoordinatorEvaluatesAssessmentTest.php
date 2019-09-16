<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

/**
 * Phase: Being assessed (assessment_under_assessment)
 *
 * @group edw
 * @group edwBrowser
 * @group assessmentWorkflow
 */
class CoordinatorEvaluatesAssessmentTest extends IucnAssessmentTestBase {

  protected function cleanupEnvironment() {
    return;
  }

  public function testChangingAssessorWhenUnderAssessment() {
    /** @var \Drupal\user\Entity\User $assessor1 */
    $assessor1 = user_load_by_mail(TestSupport::ASSESSOR1);
    /** @var \Drupal\user\Entity\User $assessor2 */
    $assessor2 = user_load_by_mail(TestSupport::ASSESSOR2);
    /** @var \Drupal\user\Entity\User $coordinator */
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);

    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor1->id(), 'field_coordinator' => $coordinator->id()]);

    $this->userLogIn(TestSupport::COORDINATOR1);
    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($stateChangeForm);

    $this->assertSession()->pageTextContains('Being assessed');

    $this->drupalPostForm($stateChangeForm, ['field_assessor' => $assessor2->id()], 'Save');

    $this->assertSession()->pageTextContains('was successfully updated.');
  }

}
