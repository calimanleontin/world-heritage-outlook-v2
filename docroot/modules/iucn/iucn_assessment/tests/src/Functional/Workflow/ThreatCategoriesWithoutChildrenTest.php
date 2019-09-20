<?php

namespace Drupal\Tests\iucn_assessment\Functional\Workflow;

use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentWebDriverTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;

/**
 * @group edw
 * @group edwWebDriver
 * @group assessmentWorkflow
 */
class ThreatCategoriesWithoutChildrenTest extends IucnAssessmentWebDriverTestBase {

  public function testThreadCategoriesWithoutChildren() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);
    $assessment->save();

    $this->userLogIn(TestSupport::COORDINATOR1);

    $term = \Drupal\taxonomy\Entity\Term::create([
      'vid' => 'assessment_threat',
      'name' => 'My threat',
    ]);
    $term->save();

    $this->drupalGet($assessment->toUrl('edit-form', ['query' => ['tab' => 'threats']]));
    $this->click('.paragraphs-icon-button-edit');
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', '#drupal-modal');
    sleep(5);

    $this->getSession()->getDriver()->selectOption("//select[@data-id='options-groups']", $term->id());
  }

}
