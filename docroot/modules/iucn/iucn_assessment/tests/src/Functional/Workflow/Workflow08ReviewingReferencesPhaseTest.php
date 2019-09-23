<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * Phase: Reference standardisation (assessment_reviewing_references)
 *
 * @group edw
 * @group edwBrowser
 * @group assessmentWorkflow
 */
class Workflow08ReviewingReferencesPhaseTest extends WorkflowTestBase {

  const WORKFLOW_STATE = AssessmentWorkflow::STATUS_REVIEWING_REFERENCES;

  public function testReviewingReferencesPhaseAccess() {
    $this->checkUserAccess($this->editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->assertSession()->pageTextContains('Current workflow state: Reference standardisation');
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REVIEWER2, 403);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER1, 200);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER1, 200);
    $this->checkUserAccess($this->editUrl, TestSupport::REFERENCES_REVIEWER2, 403);
    $this->checkUserAccess($this->stateChangeUrl, TestSupport::REFERENCES_REVIEWER2, 403);

    $this->userLogIn(TestSupport::REFERENCES_REVIEWER1);

    foreach ($this->tabs as $tab => $fields) {
      $url = $this->assessment->toUrl('edit-form', ['query' => ['tab'  => $tab]]);
      $this->drupalGet($url);
      foreach ($fields as $field) {
        $cssField = Html::cleanCssIdentifier($field);
        if ($this->assessment->get($field)->getSetting('handler') == 'default:paragraph') {
          if ($field == 'field_as_references_p') {
            $this->assertElementPresent(".field--name-field-as-references-p .field-multiple-drag");
            $this->assertElementPresent(".field--name-field-as-references-p .paragraphs-icon-button-edit");
            $this->assertElementPresent(".field--name-field-as-references-p .paragraphs-icon-button-delete");
            $this->assertElementPresent(".field--name-field-as-references-p .paragraphs-add-more-button");
          }
          else {
            $this->assertElementNotPresent(".field--name-$cssField .field-multiple-drag");
            $this->assertElementPresent(".field--name-$cssField .paragraphs-icon-button-edit");
            $this->assertElementNotPresent(".field--name-$cssField .paragraphs-icon-button-delete");
            $this->assertElementNotPresent(".field--name-$cssField .paragraphs-add-more-button");
          }
        }
        else {
          $this->assertElementPresent(".field--name-$cssField");
          $this->assertElementNotPresent(".field--name-$cssField *:disabled");
        }
      }
    }
  }

}
