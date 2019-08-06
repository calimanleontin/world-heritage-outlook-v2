<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: New (assessment_creation, assessment_new)
 *
 * @group iucn_assessment_workflow
 */
class Workflow01JsNewPhaseTest extends IucnAssessmentWebDriverTestBase {

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
    $assert_session->assertNoElementAfterWait('css', '.edit-paragraph-form-modal', 5000);
    $assessment = Node::load($assessment->id());
    $this->assertEquals(AssessmentWorkflow::STATUS_UNDER_EVALUATION, $assessment->field_state->value);
    $this->assertEquals(user_load_by_mail(TestSupport::COORDINATOR1)->id(), $assessment->field_coordinator->target_id);
  }

}
