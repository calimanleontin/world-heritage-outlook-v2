<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

/**
 * @group iucn_assessment_forms
 */
class ChangeStateFormTest extends WorkflowTestBase  {

  public function testValidation() {
    // Create valid assessment.
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION);

    // Clear node entity_reference_revisions field.
    $assessment->get('field_as_threats_current')->setValue([]);
    // Clear node optional field.
    $assessment->get('field_as_threats_potential')->setValue([]);

    // Clear node text field.
    $assessment->get('field_as_threats_text')->setValue(NULL);

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $assessment->get('field_as_values_wh')->entity;

    // Clear paragraph text field.
    $paragraph->get('field_as_description')->setValue(NULL);

    $assessment->get('field_as_vass_bio_text')->setValue(NULL);
    $paragraph->save();

    $assessment->save();

    $this->userLogIn(TestSupport::COORDINATOR1);

    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($stateChangeForm);

    // Check that only empty REQUIRED fields throw errors.
    $this->assertSession()->pageTextNotContains('Potential threats field is required');
    $this->assertSession()->pageTextContains('Current threats field is required');
    $this->assertSession()->pageTextContains('Overall Assessment of Threats field is required');
    $this->assertSession()->pageTextContains('Summary of the values - Justification of assessment field is required');
    $this->assertSession()->pageTextContains('Description field is required for all rows in Identifying and describing values table');


    // field_as_vass_bio_text is only required if field_as_values_bio is set.
    $assessment->get('field_as_values_bio')->setValue([]);
    $assessment->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()->pageTextNotContains('Summary of the values - Justification of assessment field is required');
  }

}
