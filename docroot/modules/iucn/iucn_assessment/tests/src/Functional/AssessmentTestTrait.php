<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

trait AssessmentTestTrait {

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /**
   * Helper function used to force an assessment state.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param string $newState
   *   The new state.
   * @param array $values
   *   An array of field changes.
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function setAssessmentState(NodeInterface $assessment, $newState, array $values = []) {
    foreach ($values as $field => $value) {
      $assessment->set($field, $value);
    }
    $state = $assessment->field_state->value;
    try {
      return $this->workflowService->createRevision($assessment, $newState, NULL, "{$state} ({$assessment->getRevisionId()}) => {$newState}", TRUE);
    } catch (EntityStorageException $e) {
      return NULL;
    }
  }

  /**
   * Creates a new assessment in the provided state.
   *
   * @param $state
   *  Assessment workflow state.
   * @param array $values
   *  Extra fields values.
   * @param bool $referenceUser
   *  Defaults to TRUE. If set to FALSE, the default users fields (coordinator,
   * assessor, reviewers and references reviewer) will be left empty.
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function createMockAssessmentNode($state, array $values = [], $referenceUser = TRUE) {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);

    if ($referenceUser) {
      /** @var \Drupal\user\UserInterface $coordinator */
      $coordinator = user_load_by_mail(TestSupport::COORDINATOR1);
      /** @var \Drupal\user\UserInterface $assessor */
      $assessor = user_load_by_mail(TestSupport::ASSESSOR1);
      /** @var \Drupal\user\UserInterface $reviewer1 */
      $reviewer1 = user_load_by_mail(TestSupport::REVIEWER1);
      /** @var \Drupal\user\UserInterface $reviewer2 */
      $reviewer2 = user_load_by_mail(TestSupport::REVIEWER2);
      /** @var \Drupal\user\UserInterface $referencesReviewer */
      $referencesReviewer = user_load_by_mail(TestSupport::REFERENCES_REVIEWER1);
      $values += [
        'field_coordinator' => $coordinator->id(),
        'field_assessor' => $assessor->id(),
        'field_reviewers' => [
          $reviewer1->id(),
          $reviewer2->id(),
        ],
        'field_references_reviewer' => $referencesReviewer->id(),
      ];
    }

    $states = [
      AssessmentWorkflow::STATUS_NEW,
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_REVIEW,
      AssessmentWorkflow::STATUS_FINISHED_REVIEWING,
      AssessmentWorkflow::STATUS_UNDER_COMPARISON,
      AssessmentWorkflow::STATUS_REVIEWING_REFERENCES,
      AssessmentWorkflow::STATUS_FINAL_CHANGES,
      AssessmentWorkflow::STATUS_APPROVED,
      AssessmentWorkflow::STATUS_PUBLISHED,
      AssessmentWorkflow::STATUS_DRAFT,
    ];
    $currentState = current($states);

    $assessment->save();
    $assessment = $this->setAssessmentState($assessment, $currentState, $values);

    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $stateChangeUrl = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);

    while ($currentState != $state) {
      $currentState = next($states);
      if (empty($currentState)) {
        break;
      }

      $label = $this->getAdminTransitionLabel($currentState);
      $this->drupalPostForm($stateChangeUrl, [], $label);
    }

    return Node::load($assessment->id());
  }

  /**
   * Helper function to log in as an user.
   *
   * @param string $mail
   *   The user mail.
   */
  protected function userLogIn($mail) {
    $user = user_load_by_mail($mail);
    $user->passRaw = 'password';
    $this->drupalLogin($user);
  }

  /**
   * Some button labels are overwritten for administrators (e.g. "Submit assessment"
   * for assessors becomes "Force finish assessment" for managers/administrators.
   *
   * @param $state
   *  The assessment state.
   *
   * @return string
   *  The button label.
   */
  protected function getAdminTransitionLabel($state) {
    switch ($state) {
      case AssessmentWorkflow::STATUS_READY_FOR_REVIEW:
        return 'Force finish assessment';
      case AssessmentWorkflow::STATUS_FINISHED_REVIEWING:
        return 'Force finish reviewing';
      case AssessmentWorkflow::STATUS_FINAL_CHANGES:
        return 'Force finish reference standardisation';
    }

    return WorkflowTestBase::TRANSITION_LABELS[$state];
  }

}
