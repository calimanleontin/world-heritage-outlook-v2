<?php

namespace Drupal\iucn_assessment\Tests\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\iucn_assessment\Tests\IucnAssessmentTestBase;
use Drupal\iucn_assessment\Tests\TestSupport;
use Drupal\iucn_assessment\Tests\Workflow\WorkflowTestBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * @group iucn_assessment_forms
 */
class EditFormTabsValidationTest extends IucnAssessmentTestBase {

  /**
   * Check that assessors cannot edit values.
   */
  protected function testValuesTabAccess() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();

    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalPostForm($stateChangeUrl, ['field_coordinator' => $coordinator->id()], WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
    $this->drupalPostForm($stateChangeUrl, ['field_assessor' => $assessor->id()], WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);


    $this->userLogIn(TestSupport::ASSESSOR1);

    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => 'values']]));
    $this->assertNoText('Remove"');
    $this->assertNoText('Add more"');
    $this->assertNoText('Edit"');

    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => 'assessing-values']]));
    $this->assertNoText('Remove');
    $this->assertNoText('Add more"');
    $this->assertText('Edit');
  }

  /**
   * Test that each tab on assessment edit page can be submitting without
   * the validation failing for the rest of the tabs.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTabValidation() {
    $this->userLogIn(TestSupport::COORDINATOR1);

    foreach ($this->tabs as $tab => $tabFields) {
      // We create a new assessment for each tab and try to submit the form with
      // only the fields rendered on that tab completed.
      $assessment = TestSupport::createAssessment("Assessment for {$tab} tab");
      switch ($tab) {
        case 'values':
          $whValueParagraph = Paragraph::create(['type' => 'as_site_value_wh']);
          $whValueParagraph->save();
          $assessment->get('field_as_values_wh')->appendItem($whValueParagraph);
          break;

        case 'threats':
          $threatLevel = TestSupport::getTaxonomyTerm('assessment_threat_level');
          $siteThreatParagraph = Paragraph::create(['type' => 'as_site_threat']);
          $siteThreatParagraph->save();
          $assessment->get('field_as_threats_current')->appendItem($siteThreatParagraph);

          $siteThreatParagraph = Paragraph::create(['type' => 'as_site_threat']);
          $siteThreatParagraph->save();
          $assessment->get('field_as_threats_potential')->appendItem($siteThreatParagraph);

          $siteThreatParagraph = Paragraph::create(['type' => 'as_site_threat']);
          $siteThreatParagraph->save();
          $assessment->get('field_as_threats_current')->appendItem($siteThreatParagraph);

          $siteThreatParagraph = Paragraph::create(['type' => 'as_site_threat']);
          $siteThreatParagraph->save();
          $assessment->get('field_as_threats_potential')->appendItem($siteThreatParagraph);

          $assessment->set('field_as_threats_current_text', 'text');
          $assessment->set('field_as_threats_potent_text', 'text');
          $assessment->set('field_as_threats_text', 'text');
          $assessment->set('field_as_threats_current_rating', $threatLevel->id());
          $assessment->set('field_as_threats_potent_rating', $threatLevel->id());
          $assessment->set('field_as_threats_rating', $threatLevel->id());
          break;

        case 'protection-management':
          $protectionRating = TestSupport::getTaxonomyTerm('assessment_protection_rating');
          $siteProtectionParagraph = Paragraph::create(['type' => 'as_site_protection']);
          $siteProtectionParagraph->save();
          $assessment->get('field_as_protection')->appendItem($siteProtectionParagraph);
          $assessment->set('field_as_protection_ov_text', 'text');
          $assessment->set('field_as_protection_ov_rating', $protectionRating->id());
          $assessment->set('field_as_protection_ov_out_text', 'text');
          $assessment->set('field_as_protection_ov_out_rate', $protectionRating->id());
          break;

        case 'assessing-values':
          $whValueParagraph = Paragraph::create(['type' => 'as_site_value_wh']);
          $whValueParagraph->save();
          $assessment->get('field_as_values_wh')->appendItem($whValueParagraph);
          $assessment->set('field_as_vass_wh_text', 'text');
          $assessment->set('field_as_vass_wh_state', TestSupport::getTaxonomyTerm('assessment_value_state')->id());
          $assessment->set('field_as_vass_wh_trend', TestSupport::getTaxonomyTerm('assessment_value_trend')->id());
          break;

        case 'conservation-outlook':
          $conservation_rating = TestSupport::getTaxonomyTerm('assessment_conservation_rating');
          $assessment->set('field_as_global_assessment_text', 'text');
          $assessment->set('field_as_global_assessment_level', $conservation_rating->id());
          break;

        case 'references':
          $siteReferenceParagraph = Paragraph::create(['type' => 'as_site_reference']);
          $siteReferenceParagraph->save();
          $assessment->get('field_as_references_p')->appendItem($siteReferenceParagraph);
          break;
      }
      $assessment->save();
      
      $url = $assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]);
      $this->drupalPostForm($url, [], t('Save'));
      $this->assertRaw('has been updated.', "The form was succesfully submitted for {$tab} tab.");
    }
  }

}
