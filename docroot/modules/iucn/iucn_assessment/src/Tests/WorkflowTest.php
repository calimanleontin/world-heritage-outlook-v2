<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowConfigTransition;

/**
 * Defines test scenarios for the assessment workflow.
 *
 * @package Drupal\iucn_assessment\Tests
 * @group iucn
 */
class WorkflowTest extends IucnAssessmentTestBase {

  const TRANSITION_LABELS = [
    AssessmentWorkflow::STATUS_NEW => 'Save',
    AssessmentWorkflow::STATUS_UNDER_EVALUATION => 'Initiate assessment',
    AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => 'Send to assessor',
    AssessmentWorkflow::STATUS_READY_FOR_REVIEW => 'Finish assessment',
    AssessmentWorkflow::STATUS_UNDER_REVIEW => 'Send assessment to reviewers',
    AssessmentWorkflow::STATUS_FINISHED_REVIEWING => 'Finish reviewing',
    AssessmentWorkflow::STATUS_UNDER_COMPARISON => 'Start comparing reviews',
    AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => 'Review references',
    AssessmentWorkflow::STATUS_APPROVED => 'Approve',
    AssessmentWorkflow::STATUS_PUBLISHED => 'Publish',
    AssessmentWorkflow::STATUS_DRAFT => 'Start working on a draft',
  ];

  public function checkUserAccess(Url $url, $user, $expectedResponseCode) {
    $this->userLogIn($user);
    $this->drupalGet($url);
    $this->assertResponse($expectedResponseCode, "User {$user} tries to access {$url->toString()} and the HTTP response code should be {$expectedResponseCode}.");
  }

  /**
   * Test the assessment workflow, going through all the states.
   * Loop an assessment through all the states and check all users' edit access.
   */
  public function testWorkflowTransitionsAndAccess() {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment);
    $assessment->save();

    $editUrl = $assessment->toUrl('edit-form', ['query' => ['tab' => 'protection-management']]);
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);

    // Assessment state: NEW
    $this->assert('pass', "Testing assessment state: NEW");
    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 200);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);

    // Assessment state: UNDER EVALUATION
    $this->assert('pass', "Testing assessment state: UNDER EVALUATION");
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, [], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_EVALUATION]);
    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);

    // Assessment state: UNDER ASSESSMENT
    $this->assert('pass', "Testing assessment state: UNDER ASSESSMENT");
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalPostForm($stateChangeUrl, ['field_assessor' => $assessor->id()], static::TRANSITION_LABELS[AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);
    $this->checkUserAccess($editUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ADMINISTRATOR, 200);
    $this->checkUserAccess($editUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::IUCN_MANAGER, 200);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR1, 403);
    $this->checkUserAccess($editUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::COORDINATOR2, 403);
    $this->checkUserAccess($editUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($stateChangeUrl, TestSupport::ASSESSOR1, 200);
    $this->checkUserAccess($editUrl, TestSupport::REVIEWER1, 403);
    $this->checkUserAccess($stateChangeUrl, TestSupport::REVIEWER1, 403);
  }

  /**
   * Check that revisions are created correctly.
   */
  protected function testRevisions() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $reviewer = user_load_by_mail(TestSupport::REVIEWER1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    // Move the assessment through 5 states. We should have 5 revisions.
    // Additionally we should have a revision created for the reviewer.
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_REVIEW, ['field_reviewers' => $reviewer->id()]);
    $assessment_revisions_ids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($assessment);

    $this->assertEqual(count($assessment_revisions_ids), 6, 'There should be 6 revisions, 5 for each state and 1 for the reviewer.');
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $reviewer_revision = $workflow_service->getReviewerRevision($assessment, $reviewer->id());
    $this->assertTrue(!empty($reviewer_revision), 'Reviewer revision was created.');
    $this->userLogIn(TestSupport::REVIEWER1);
    $this->drupalGet($assessment->toUrl('edit-form'));
    $url = $this->getUrl();
    $node_revision_url = Url::fromRoute('node.revision_edit',
      ['node' => $assessment->id(), 'node_revision' => $reviewer_revision->vid->value])
      ->setAbsolute(TRUE)
      ->toString();

    $this->assertEqual($url, $node_revision_url, 'Successfully redirected reviewer.');
    $this->assertEqual($reviewer_revision->getRevisionUserId(), $reviewer->id());
    $this->assertTrue(!$reviewer_revision->isDefaultRevision());

    $reviewer_revision->setTitle('test_1');
    $reviewer_revision->save();
    $reviewer_revision = $workflow_service->getReviewerRevision($assessment, $reviewer->id());

    $assessment->setTitle('test_2');
    $assessment->save();
    $assessment = Node::load($assessment->id());

    $under_evaluation_revision = $workflow_service->getRevisionByState($assessment, $workflow_service::STATUS_UNDER_EVALUATION);
    $under_evaluation_revision->setTitle('test_3');
    $under_evaluation_revision->save();
    $under_evaluation_revision = $workflow_service->getRevisionByState($assessment, $workflow_service::STATUS_UNDER_EVALUATION);

    // Check that editing a revision doesn't alter the other revisions.
    $this->assertNotEqual($reviewer_revision->getTitle(), $assessment->getTitle());
    $this->assertNotEqual($reviewer_revision->getTitle(), $under_evaluation_revision->getTitle());
    $this->assertNotEqual($assessment->getTitle(), $under_evaluation_revision->getTitle());

    $paragraph1 = Paragraph::create([
      'type' => 'as_site_value_wh',
    ]);
    $paragraph1->save();

    // Check that adding a paragraph to a revision
    // doesn't add it to the main revision.
    $under_evaluation_revision->field_as_values_wh->appendItem($paragraph1);
    $under_evaluation_revision->save();
    $under_evaluation_revision = $workflow_service->getRevisionByState($assessment, $workflow_service::STATUS_UNDER_EVALUATION);
    $paragraphs = $under_evaluation_revision->field_as_values_wh->getValue();
    $this->assertTrue(!empty($paragraphs), 'Paragraph added to older revision.');
    $assessment = Node::load($assessment->id());
    $paragraphs = $assessment->field_as_values_wh->getValue();
    $this->assertTrue(empty($paragraphs), 'Paragraph not added to default revision.');

    $paragraph2 = Paragraph::create([
      'type' => 'as_site_value_wh',
    ]);
    $paragraph2->save();

    // Check that adding a paragraph to the main revision
    // doesn't alter older revisions.
    $assessment->field_as_values_wh->appendItem($paragraph2);
    $assessment->save();
    $under_evaluation_revision = $workflow_service->getRevisionByState($assessment, $workflow_service::STATUS_UNDER_EVALUATION);
    $this->assertEqual(count($under_evaluation_revision->field_as_values_wh->getValue()), 1, 'Paragraph not added to revision.');
  }

  /**
   * Check that deleted paragraphs are shown in red .
   */
  protected function testDeletedParagraphs() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $this->userLogIn(TestSupport::COORDINATOR1);

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);

    // Create Paragraph of type 'as_site_value_wh' and add it on assessment1->field_as_values_wh
    $paragraph1 = Paragraph::create([
      'type' => 'as_site_value_wh',
    ]);
    $paragraph1->save();

    $assessment->field_as_values_wh->appendItem($paragraph1);
    $assessment->save();

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

    // Delete the paragraph inside field_as_values_wh
    $assessment->field_as_values_wh->setValue([]);
    $assessment->save();

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);

    // Open the assessment page as coordinator and check that there is a deleted paragraph row (css-class: paragraph-deleted-row)
    // with a revert button (value="Revert")
    drupal_flush_all_caches();
    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalGet($assessment->toUrl('edit-form'));

    $this->assertRaw('paragraph-deleted-row');
    $this->assertRaw('value="Revert"');
  }
}
