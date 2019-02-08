<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class EditFormTest extends  IucnAssessmentTestBase {

  protected $paragraphCounter;

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


    $this->userLogIn(TestSupport::COORDINATOR1);
    $assessment = $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    $assessment = $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

    drupal_flush_all_caches();

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

    $tabs = [
      'values',
      'threats',
      'protection-management',
      'assessing-values',
      'conservation-outlook',
      'benefits',
      'projects',
      'references',
    ];

    foreach ($tabs as $tab) {
      // We create a new assessment for each tab and try to submit the form with
      // only the fields rendered on that tab completed.
      $assessment = TestSupport::createAssessment("Assessment for {$tab} tab");
      switch ($tab) {
        case 'values':
          $whValueParagraph = Paragraph::create(['type' => 'as_site_value_wh']);
          $whValueParagraph->save();
          $assessment->field_as_values_wh->appendItem($whValueParagraph);
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
          $assessment->field_as_values_wh->appendItem($whValueParagraph);
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
   * Check that if the 'View diff' button appears on all types of paragraphs.
   */
  protected function testViewDiffForAllParagraphs() {
    $this->paragraphCounter = [];
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
    $this->userLogIn(TestSupport::COORDINATOR1);

    // For every field that shows up in a tab, we create an empty paragraph of a
    // certain bundle (e.g. as_site_value_wh) and append it to it's
    // corresponding assessment field.


    //    2 - Paragraphs belonging to the 'Threats' tab

    $assessment = TestSupport::createAssessment();
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    // -- "Current threats"
    for ($x = 0; $x < 13; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_threat',
      ]);

      $paragraph2 = Paragraph::create([
        'type' => 'as_site_value_wh',
        'field_machine_name' => 'p1_wh'
      ]);
      $paragraph2->save();
      $paragraph3 = Paragraph::create([
        'type' => 'as_site_value_bio',
        'field_machine_name' => 'p1_bio'
      ]);
      $paragraph3->save();

      $paragraph->field_as_threats_values_wh->appendItem($paragraph2);
      $paragraph->field_as_threats_values_bio->appendItem($paragraph3);
      $paragraph->save();

      $assessment->field_as_threats_current->appendItem($paragraph);
    }
    $assessment->save();

    // -- "Potential threats"
    for ($x = 0; $x < 13; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_threat',
      ]);

      $paragraph2 = Paragraph::create([
        'type' => 'as_site_value_wh',
        'field_machine_name' => 'p1_wh'
      ]);
      $paragraph2->save();
      $paragraph3 = Paragraph::create([
        'type' => 'as_site_value_bio',
        'field_machine_name' => 'p1_bio'
      ]);
      $paragraph3->save();

      $paragraph->field_as_threats_values_wh->appendItem($paragraph2);
      $paragraph->field_as_threats_values_bio->appendItem($paragraph3);
      $paragraph->save();

      $assessment->field_as_threats_potential->appendItem($paragraph);
    }
    $assessment->save();

    //    3 - Paragraphs belonging to the "Protection and management" tab

    // -- "Assessing protection and management"
    for ($x = 0; $x < 3; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_protection',
      ]);
      $paragraph->save();

      $assessment->field_as_protection->appendItem($paragraph);
    }
    $assessment->save();

    //    4 - Paragraphs belonging to the "Assessing values" tab

    // -- "Assessing the current state and trend of values"
    for ($x = 0; $x < 4; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_value_wh',
      ]);
      $paragraph->save();

      $assessment->field_as_values_wh->appendItem($paragraph);
    }
    $assessment->save();

    //    6 - Paragraphs belonging to the "Benefits" tab

    // -- "Understanding benefits"
    for ($x = 0; $x < 9; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_benefit',
      ]);
      $paragraph->save();

      $assessment->field_as_benefits->appendItem($paragraph);
    }
    $assessment->save();

    //    7 - Paragraphs belonging to the "Projects" tab

    // -- "Compilation of active conservation projects"
    for ($x = 0; $x < 3; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_project',
      ]);
      $paragraph->save();

      $assessment->field_as_projects->appendItem($paragraph);
    }
    $assessment->save();

    //    8 - Paragraphs belonging to the "References" tab

    // -- "References"
    for ($x = 0; $x < 1; $x++) {
      $paragraph = Paragraph::create([
        'type' => 'as_site_reference',
      ]);
      $paragraph->save();

      $assessment->field_as_references_p->appendItem($paragraph);
    }
    $assessment->save();


    // <-----> Here we log in as an assessor and we update the values of the created paragraphs and fields examples
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);
    drupal_flush_all_caches();
    $this->userLogIn(TestSupport::ASSESSOR1);

    foreach ($assessment->getFields() as $field) {
      if (strpos($field->getName(), "field_as_") === 0 && !in_array($field->getName(), ['field_as_start_date', 'field_as_end_date'])) { // check if the field can be revised
        $this->updateTestingField($field, $assessment);
      } else { // if the field is not included in the Diff
        continue;
      }
    }
    $assessment->save();

    // <-----> Now we go to the edit page as a coordinator and iterate through each tab so we can verify the existence of the 'View Diff' buttons
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);
    drupal_flush_all_caches();
    $this->userLogIn(TestSupport::COORDINATOR1);

    foreach (['threats', 'protection-management', 'assessing-values', 'conservation-outlook', 'benefits', 'projects', 'references'] as $tab) {
      $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]));
      $haystack = $this->getRawContent();
      $needle = 'value="See differences"';
      $lastPos = 0;
      $num = 0;
      while (($lastPos = strpos($haystack, $needle, $lastPos))!== false) {
        $num++;
        $lastPos = $lastPos + strlen($needle);
      }
      $num--; // Keep in mind that we do not count the 'See differences' button for the uploaded pdf
      $expectedNo = 0;
      foreach (TestSupport::TABS_WITH_FIELD_AND_PARAGRAPH_TYPES[$tab] as $item) {
        if (!empty($this->paragraphCounter[$item])) {
          $expectedNo += $this->paragraphCounter[$item];
        }
      }
      if ($expectedNo != $num) {
        $this->assert('fail', "There is a difference between the number of modifications and the number of 'See differences' buttons in your ' $tab ' tab.");
      }
      $this->assert('pass', "Number of paragraph modifications for the '" . $tab . "' tab' : " . $expectedNo);
      $this->assert('pass', "Number of 'See differences' buttons for the '" . $tab . "' tab' : " . $num);
      if ($num == 0) {
        $this->assert('fail', "The 'See differences' button has not appeared in the tab '" . $tab . "'.");
      }
    }
  }

  /**
   * Update the field/paragraph depending on it's field type.
   *
   * @param null $field
   *  The field that will be updated.
   * @param null $entity
   *  The assessment to which the field belongs to.
   *
   */
  protected function updateTestingField($field, $entity) {
    switch ($field->getFieldDefinition()->getType()) {
      case "boolean":
        if ($field->value == "1") {
          $new_value = "0";
        } else {
          $new_value = "1";
        }
        $entity->set($field->getName(), $new_value);
        $this->paragraphCounter[$field->getName()] = 1;
        break;
      case "string_long":
        $entity->set($field->getName(), 'Updated text.');
        $this->paragraphCounter[$field->getName()] = 1;
        break;
      case "string":
        $entity->set($field->getName(), 'Updated text.');
        $this->paragraphCounter[$field->getName()] = 1;
        break;
      //      case "datetime":
      //        // TODO
      //        break;
      case "float":
        $entity->set($field->getName(), $field->value + 1.0);
        $this->paragraphCounter[$field->getName()] = 1;
        break;
      case "list_string":
        $entity->set($field->getName(), ['Updated text.']);
        $this->paragraphCounter[$field->getName()] = 1;
        break;
      case "entity_reference": // Nodes and Taxonomy terms
        if ($field->getFieldDefinition()->getSetting('target_type') == "taxonomy_term") {
          // We load a new taxonomy term for the vocabulary used in the select options of this field
          $targetBundles = $field->getFieldDefinition()->getSetting('handler_settings')['target_bundles'];
          $term = TestSupport::getTaxonomyTerm(reset($targetBundles), 2);
          $entity->set($field->getName(), $term->id());
          $this->paragraphCounter[$field->getName()] = 1;
        } else if ($field->getFieldDefinition()->getSetting('target_type') == "node") {
          $node = Node::create([
            'type'        => reset($field->getFieldDefinition()->getSetting('handler_settings')['target_bundles']),
            'title'       => $field->getName() . '_test_node',
          ]);
          $node->save();
          $entity->set($field->getName(), $node->id());
          $this->paragraphCounter[$field->getName()] = 1;
        } else {
          $this->assert('fail', "The type of entity referenced in the field '" . $field->getName() . "' is not revisable.");
        }
        break;
      case "entity_reference_revisions": // Paragraphs
        /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field */
        if ($field->getName() == "field_as_threats_values_bio") {
          $paragraph = Paragraph::create([
            'type' => 'as_site_value_bio',
            'field_machine_name' => 'p2_bio'
          ]);
          $paragraph->save();

          $entity->field_as_threats_values_bio->appendItem($paragraph);
          $entity->save();
        } else if ($field->getName() == "field_as_threats_values_wh") {
          $paragraph = Paragraph::create([
            'type' => 'as_site_value_wh',
            'field_machine_name' => 'p2_wh'
          ]);
          $paragraph->save();

          $entity->field_as_threats_values_wh->appendItem($paragraph);
          $entity->save();
        } else { // We enter this block of code if the field is not a paragraph inside a paragraph(like the special cases solved in the above conditionals)
          $pgphNo = 0;
          if (!empty($field->getValue())) {
            $value = $field[0]->getValue();
            $defaultParagraph = Paragraph::load($value['target_id']);
            foreach ($defaultParagraph->getFields() as $pgphField) {
              // This particular check is for 2 fields that appear on multiple paragraphs but are hidden only in the 'as_site_value_wh' paragraph type
              if ($field->getName() == 'field_as_values_wh' && ($pgphField->getName() == "field_as_description" || $pgphField->getName() == "field_as_values_criteria")) {
                continue;
              }
              // First we check if the field can be revised and it is not hidden
              if (strpos($pgphField->getName(), "field_") === 0 && !in_array($pgphField->getName(), TestSupport::HIDDEN_PARAGRAPH_FIELDS) && !empty($field[$pgphNo])) {
                // Finally we update the field of the paragraphs because it passed all the verifications
                $value = $field[$pgphNo]->getValue();
                $childParagraph = Paragraph::load($value['target_id']);
                $this->updateTestingField($childParagraph->get($pgphField->getName()), $childParagraph);
                $pgphNo++;
              }
              else {
                continue;
              }
            }
            // Now we save the number of updated paragraphs for each entity_reference_revisions field so we know how many 'See differences' buttons we need to count in the final assert
            if (empty($this->paragraphCounter[$defaultParagraph->bundle()])) {
              $this->paragraphCounter[$defaultParagraph->bundle()] = $pgphNo;
            }
            else {
              $this->paragraphCounter[$defaultParagraph->bundle()] += $pgphNo;
            }
          }
        }
        break;
      default:
        $this->assert('fail', "The '" . $field->getFieldDefinition()->getType() . "' field type is not revisable.");
        break;
    }
    $entity->save();
  }
}
