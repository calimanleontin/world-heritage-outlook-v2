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

  public function testStateAssessmentChangeToUnderEvaluation() {
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_NEW, [], TRUE);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($assessment->toUrl('edit-form'), [], t('Save'));

    $assessment = Node::load($assessment->id());
    $this->assertEquals(AssessmentWorkflow::STATUS_UNDER_EVALUATION, $assessment->field_state->value);
    $this->assertEquals(user_load_by_mail(TestSupport::COORDINATOR1)->id(), $assessment->field_coordinator->target_id);

    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_NEW, [], TRUE);
    $this->drupalGet($assessment->toUrl('edit-form'));
    $this->click('#edit-field-as-values-wh-0-top-actions-buttons-edit');
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '.edit-paragraph-form-modal .button--primary');

    $this->click('.edit-paragraph-form-modal .ui-dialog-buttonpane .button--primary');
    sleep(10);
    $assessment = Node::load($assessment->id());
    $this->assertEquals(AssessmentWorkflow::STATUS_UNDER_EVALUATION, $assessment->field_state->value);
    $this->assertEquals(user_load_by_mail(TestSupport::COORDINATOR1)->id(), $assessment->field_coordinator->target_id);

  }

}


