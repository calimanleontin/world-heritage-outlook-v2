<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Pre-assessment edits (assessment_under_evaluation)
 *
 * @group edw
 * @group edwWebDriver
 * @group assessmentWorkflow
 */
class Workflow02JsUnderEvaluationPhaseTest extends IucnAssessmentWebDriverTestBase {

  public function testWarningAppearance() {
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION, []);
    $this->userLogIn(TestSupport::COORDINATOR1);

    $paragraph = $assessment->field_as_threats_current->entity;

    /** @var Paragraph $value */
    $value = $assessment->field_as_values_wh->entity;
    $paragraph->field_as_threats_values_wh->setValue([
      'target_id' => $value->id(),
      'target_revision_id' => $value->getRevisionId(),
    ]);
    $paragraph->field_as_threats_values_bio->setValue([]);
    $paragraph->save();
    $assessment = Node::load($assessment->id());
    $this->drupalGet($assessment->toUrl('edit-form'));

    $this->click('#edit-field-as-values-wh-0-top-actions-buttons-delete');
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '#drupal-modal');
    sleep(5);
    $assert_session->responseContains('This value cannot be deleted because it is the only affected value for the some threats. Please edit or delete these threats first');
  }

}
