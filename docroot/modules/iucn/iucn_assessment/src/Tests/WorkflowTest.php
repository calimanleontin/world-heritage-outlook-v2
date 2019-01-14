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

  protected $hasDraftRevision;
  protected $paragraphCounter;

  /**
   * Test the assessment workflow, going through all the states.
   */
  public function testAssessmentWorkflowAccess() {
    $assessment = TestSupport::getNodeByTitle(TestSupport::ASSESSMENT1);

    $this->checkAccessOnEveryState($assessment);
  }

  /**
   * Tests an user access on an assessment edit page.
   *
   * If the vid parameter is passed,
   * the test will be done on the revision edit page.
   *
   * @param string $mail
   *   The user mail.
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param int $assert_response_code
   *   The response code that the visited page should return.
   * @param int $vid
   *   The revision id.
   */
  protected function assertUserAccessOnAssessmentEdit($mail, NodeInterface $assessment, $assert_response_code, $vid = NULL) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    if (empty($vid)) {
      $url = $assessment->toUrl('edit-form');
    }
    else {
      $url = Url::fromRoute('node.revision_edit', ['node' => $assessment->id(), 'node_revision' => $vid]);
    }
    $this->userLogIn($mail);
    $this->drupalGet($url);
    $this->assertResponse($assert_response_code);
  }

  /**
   * Check all users' access on an assessment edit page.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   */
  protected function assertAllUserAccessOnAssessmentEdit(NodeInterface $assessment) {
    $state = $assessment->field_state->value;
    // Administrators and managers can edit any assessment, regardless of state.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::ADMINISTRATOR, $assessment, 200);
    $this->assertUserAccessOnAssessmentEdit(TestSupport::IUCN_MANAGER, $assessment, 200);
    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT || $state == AssessmentWorkflow::STATUS_NEW) {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 403);
    }
    else {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 200);
    }

    // Assessor 1 should only be able to edit in the under_assessment state.
    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT) {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 200);
    }
    else {
      $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 403);
    }

    // Coordinator 2 can only never edit.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::COORDINATOR2, $assessment, 403);


    // Assessor 2 is never allowed to edit this assessment.
    $this->assertUserAccessOnAssessmentEdit(TestSupport::REVIEWER1, $assessment, 403);
    $this->assertUserAccessOnAssessmentEdit(TestSupport::ASSESSOR2, $assessment, 403);
    $this->assertUserAccessOnAssessmentEdit(TestSupport::REVIEWER2, $assessment, 403);
  }

  /**
   * Loop an assessment through all the states and check all users' edit access.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   */
  protected function checkAccessOnEveryState(NodeInterface $assessment) {
    $states = [
      AssessmentWorkflow::STATUS_NEW,
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_REVIEW,
      AssessmentWorkflow::STATUS_FINISHED_REVIEWING,
      AssessmentWorkflow::STATUS_UNDER_COMPARISON,
      AssessmentWorkflow::STATUS_REVIEWING_REFERENCES,
      AssessmentWorkflow::STATUS_APPROVED,
      AssessmentWorkflow::STATUS_PUBLISHED,
      AssessmentWorkflow::STATUS_DRAFT,
    ];
    foreach ($states as $state) {
      $field_changes = NULL;
      if ($state == AssessmentWorkflow::STATUS_UNDER_EVALUATION) {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::COORDINATOR1);
        $field_changes = ['field_coordinator' => $user->id()];
      }
      if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT) {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::ASSESSOR1);
        $field_changes = ['field_assessor' => $user->id()];
      }
      elseif ($state == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
        /** @var \Drupal\user\Entity\User $user */
        $user = user_load_by_mail(TestSupport::REVIEWER1);
        $field_changes = ['field_reviewers' => $user->id()];
      }
      $this->setAssessmentState($assessment, $state, $field_changes);
      if ($state != AssessmentWorkflow::STATUS_DRAFT) {
        $this->assertEqual($assessment->field_state->value, $state, "Testing state: $state");
      }
      else {
        // The state of the assessment is never draft.
        // We only have draft revisions.
        $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_PUBLISHED, "Testing state: $state");
        $this->hasDraftRevision = TRUE;
      }
      $this->assertAllUserAccessOnAssessmentEdit($assessment);
    }
  }

  /**
   * Make sure transition conditions are respected.
   *
   * Moving to state under_evaluation requires a coordinator.
   * Moving to state under_assessment requires an assessor.
   * Moving to state under_review requires a reviewer.
   */
  protected function testValidTransitions() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->userLogIn(TestSupport::COORDINATOR1);
    $url = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    $this->drupalGet($url);
    $this->assertText(t('Coordinator'));
    $this->assertNoText(t('Assessor'));
    $this->assertNoText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_new_under_evaluation');
    $button_label = $transition->label();
    // Try to change the state without assigning coordinator.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_NEW);

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $coordinator1 = user_load_by_mail(TestSupport::COORDINATOR1);
    $this->drupalPostForm($url, ['field_coordinator' => $coordinator1->id()], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_UNDER_EVALUATION);

    $this->drupalGet($url);
    $this->assertNoText(t('Coordinator'));
    $this->assertText(t('Assessor'));
    $this->assertNoText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_under_evaluation_under_assessment');
    $button_label = $transition->label();
    // Try to change the state without assigning assessor.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_UNDER_EVALUATION);

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $assessor1 = user_load_by_mail(TestSupport::ASSESSOR1);
    $this->drupalPostForm($url, ['field_assessor' => $assessor1->id()], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);

    $this->userLogIn(TestSupport::ASSESSOR1);
    $transition = WorkflowConfigTransition::load('assessment_under_assessment_ready_for_review');
    $button_label = $transition->label();
    $this->drupalPostForm($url, [], t($button_label));
    drupal_flush_all_caches();

    $this->userLogIn(TestSupport::COORDINATOR1);
    $this->drupalGet($url);
    $this->assertNoText(t('Coordinator'));
    $this->assertNoText(t('Assessor'));
    $this->assertText(t('Reviewers'));

    $transition = WorkflowConfigTransition::load('assessment_ready_for_review_under_review');
    $button_label = $transition->label();
    // Try to change the state without assigning reviewer.
    $this->drupalPostForm($url, [], t($button_label));
    // Reload node.
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);

    /** @var \Drupal\user\Entity\User $coordinator1 */
    $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
    $this->drupalPostForm($url, ['field_reviewers[]' => [$reviewer1->id()]], t($button_label));
    drupal_flush_all_caches();
    $assessment = Node::load($assessment->id());
    $this->assertEqual($assessment->field_state->value, AssessmentWorkflow::STATUS_UNDER_REVIEW);
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
   * Check that assessors cannot edit values.
   */
  protected function testValuesTabAccess() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $paragraph = Paragraph::create([
      'type' => 'as_site_value_wh',
    ]);
    $paragraph->save();
    $assessment->field_as_values_wh->appendItem($paragraph);
    $assessment->save();

    $this->userLogIn(TestSupport::COORDINATOR1);

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

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

  /**
   * Check that if the 'View diff' button works correctly on all types of paragraphs.
   */
  protected function testViewDiffForAllParagraphs() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $this->userLogIn(TestSupport::COORDINATOR1);

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION, ['field_coordinator' => $coordinator->id()]);

    // <-----> For every field that shows up in a tab, we create an empty paragraph of a certain bundle (e.g. as_site_value_wh) and append it to it's corresponding assessment field.

    $this->paragraphCounter = [];

    //    2 - Paragraphs belonging to the 'Threats' tab

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
          $term_id = TestSupport::getTidByName(
            reset($field->getFieldDefinition()->getSetting('handler_settings')['target_bundles']) . '_term2',
            reset($field->getFieldDefinition()->getSetting('handler_settings')['target_bundles'])
          );
          $entity->set($field->getName(), $term_id);
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
