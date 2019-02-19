<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * @group iucn
 */
class EditFormTest extends IucnAssessmentTestBase {

  protected $tabs = [
    'values' => [
      'field_as_values_wh',
      'field_as_values_bio',
    ],
    'threats' => [
      'field_as_threats_current',
      'field_as_threats_potential',
      'field_as_threats_current_text',
      'field_as_threats_current_rating',
      'field_as_threats_potent_text',
      'field_as_threats_potent_rating',
      'field_as_threats_text',
      'field_as_threats_rating',
    ],
    'protection-management' => [
      'field_as_protection',
      'field_as_protection_ov_text',
      'field_as_protection_ov_rating',
      'field_as_protection_ov_out_text',
      'field_as_protection_ov_out_rate',
      'field_as_protection_ov_practices',
    ],
    'assessing-values' => [
      'field_as_values_wh',
      'field_as_vass_wh_text',
      'field_as_vass_wh_state',
      'field_as_vass_wh_trend',
      'field_as_vass_bio_text',
      'field_as_vass_bio_state',
      'field_as_vass_bio_trend',
    ],
    'conservation-outlook' => [
      'field_as_global_assessment_text',
      'field_as_global_assessment_level',
    ],
    'benefits' => [
      'field_as_benefits',
      'field_as_benefits_summary',
    ],
    'projects' => [
      'field_as_projects',
    ],
    'references' => [
      'field_as_references_p',
    ],
  ];

  /**
   * Check that assessors cannot edit values.
   */
  protected function testValuesTabAccess() {
    $assessment = TestSupport::createAssessment();
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $paragraph = Paragraph::create([
      'type' => 'as_site_value_wh',
    ]);
    $paragraph->save();
    $assessment->get('field_as_values_wh')->appendItem($paragraph);
    $assessment->save();

    $assessment = $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    $assessment = $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

    $this->userLogIn(TestSupport::ASSESSOR1);

    foreach (['values', 'assessing-values'] as $tab) {
      $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]));
      $this->assertNoRaw('tabledrag-handle');
      $this->assertNoRaw('value="Remove"');
      $this->assertNoRaw('value="Add more"');
      $this->assertRaw('Save');
      if ($tab == 'values') {
        $this->assertNoRaw('value="Edit"');
      }
      else {
        $this->assertRaw('value="Edit"');
      }
    }
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
          $assessment->set('field_as_global_assessment_text', 'text');
          $assessment->set('field_as_global_assessment_level', TestSupport::getTaxonomyTerm('assessment_conservation_rating')->id());
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

  /**
   * Check that if the 'View diff' button appears on all tabs for every fields
   * that can be edited.
   */
  protected function testDifferences() {
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

    $expectedDifferences = [];

    foreach ($this->tabs as $tab => $fields) {
      $expectedDifferences[$tab] = 0;
      if ($tab == 'values') {
        // Other users can't edit values so we can't have differences on this tab.
        continue;
      }
      foreach ($fields as $field) {
        $fieldItemList = $assessment->get($field);
        /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
        $fieldDefinition = $fieldItemList->getFieldDefinition();
        if ($fieldDefinition->getType() == 'entity_reference_revisions') {
          $handlerSettings = $fieldDefinition->getSetting('handler_settings');
          $targetType = $fieldDefinition->getSetting('target_type');
          $targetBundle = reset($handlerSettings['target_bundles']);

          $childFields = array_keys($this->entityFieldManager->getFieldDefinitions($targetType, $targetBundle));
          $childFields = array_values(array_filter($childFields, function ($field) {
            return preg_match('/^field\_/', $field);
          }));

          foreach ($childFields as $i => $childField) {
            // We update only one field for each child entity to test if the
            // differences are retrieved for all fields.
            $childValue = $fieldItemList->get($i);
            /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
            $childEntity = $childValue->entity;
            TestSupport::updateFieldData($childEntity, $childField);
            $childEntity->save();
            $fieldItemList->set($i, $childEntity);
            $expectedDifferences[$tab]++;
          }
        }
        else {
          TestSupport::updateFieldData($assessment, $field);
          $expectedDifferences[$tab]++;
        }
        $assessment->set($field, $fieldItemList->getValue());
      }
    }
    $assessment->save();

    // Access denied?!
    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm(Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]), [], 'Finish assessment');

    $this->userLogIn(TestSupport::COORDINATOR1);
    foreach ($this->tabs as $tab => $fields) {
      $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]));
      $actualDifferences = substr_count($this->getRawContent(), 'value="See differences"');
      $this->assertEqual($expectedDifferences[$tab], $actualDifferences, "Expected {$expectedDifferences[$tab]} differences on \"{$tab}\" tab, {$actualDifferences} found.");
    }
  }

}
