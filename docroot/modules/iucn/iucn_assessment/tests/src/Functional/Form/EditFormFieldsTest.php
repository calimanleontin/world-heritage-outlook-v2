<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group edw
 * @group edwWebDriver
 * @group assessmentWorkflow
 */
class EditFormFieldsTest extends IucnAssessmentWebDriverTestBase {

  public function testThreatCategoriesWithoutChildren() {
    //Test that terms from vocabulary assessment_threat without children appear on edit paragraph select
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();

    $this->userLogIn(TestSupport::COORDINATOR1);

    $term = Term::create([
      'vid' => 'assessment_threat',
      'name' => 'My threat',
    ]);
    $term->save();

    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => 'threats']]));
    $this->click('.paragraphs-icon-button-edit');
    $this->assertSession()->waitForElement('css', '#drupal-modal');

    $this->getSession()->getDriver()->selectOption("//select[@data-id='options-groups']", $term->id());
  }

}
