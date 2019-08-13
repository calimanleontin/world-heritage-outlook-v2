<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class EditFormDifferencesTest extends IucnAssessmentTestBase {

  /**
   * Check that if the 'View diff' button appears on all tabs for every fields
   * that can be edited.
   */
  protected function testDifferences() {
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $childrenFields = [];

    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 0);
    foreach ($this->tabs as $tab => $fields) {
      $expectedDifferences[$tab] = 0;
      foreach ($fields as $field) {
        if ($field == 'field_as_threats_potential') {
          continue;
        }
        /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
        $fieldDefinition = $assessment->get($field)->getFieldDefinition();
        if ($fieldDefinition->getType() == 'entity_reference_revisions') {
          $handlerSettings = $fieldDefinition->getSetting('handler_settings');
          $targetType = $fieldDefinition->getSetting('target_type');
          $targetBundle = reset($handlerSettings['target_bundles']);

          $childFields = array_keys($this->entityFieldManager->getFieldDefinitions($targetType, $targetBundle));
          $childrenFields[$field] = array_values(array_filter($childFields, function ($field) {
            // We skip testing for the following 2 fields.
            if ($field == 'field_as_threats_values_bio') return FALSE;
            if ($field == 'field_as_threats_values_wh') return FALSE;
            return preg_match('/^field\_/', $field);
          }));
          TestSupport::updateFieldData($assessment, $field, count($childrenFields[$field]));
        }
      }
    }
    $assessment->save();

    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalPostForm($stateChangeUrl, ['field_coordinator' => $coordinator->id()], WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
    $this->drupalPostForm($stateChangeUrl, ['field_assessor' => $assessor->id()], WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);

    $assessment = Node::load($assessment->id());

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
        if (!empty($childrenFields[$field])) {
          foreach ($childrenFields[$field] as $i => $childField) {
            // We update only one field for each child entity to test if the
            // differences are retrieved for all fields.
            $childValue = $fieldItemList->get($i);
            /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
            $childEntity = $childValue->entity;
            TestSupport::updateFieldData($childEntity, $childField);
            $childEntity->save();
            $expectedDifferences[$tab]++;
          }
          $assessment->set($field, $fieldItemList->getValue());
        }
        elseif ($fieldDefinition->getType() != 'entity_reference_revisions') {
          TestSupport::updateFieldData($assessment, $field);
          $expectedDifferences[$tab]++;
        }
      }
    }
    $assessment->save();

    $this->userLogIn(TestSupport::ASSESSOR1);
    $this->drupalPostForm($stateChangeUrl, [], WorkflowTestBase::TRANSITION_LABELS[AssessmentWorkflow::STATUS_READY_FOR_REVIEW]);

    $this->userLogIn(TestSupport::COORDINATOR1);
    foreach ($this->tabs as $tab => $fields) {
      // Other users can't edit values so we can't have differences on this tab.
      if ($tab == 'values') {
        continue;
      }
      $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]));
      $actualDifferences = substr_count($this->getTextContent(), 'See differences');
      $this->assertEquals($expectedDifferences[$tab], $actualDifferences, "Expected {$expectedDifferences[$tab]} differences on \"{$tab}\" tab, {$actualDifferences} found.");
    }
  }

}
