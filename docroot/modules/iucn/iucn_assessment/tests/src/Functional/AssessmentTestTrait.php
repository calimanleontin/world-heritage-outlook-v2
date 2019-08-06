<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\NodeInterface;

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
    }
    catch (EntityStorageException $e) {
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
   * @param bool $doNotReferenceUser
   *  Defaults to FALSE. If set to TRUE, the default users fields (coordinator,
   * assessor, reviewers and references reviewer) will be left empty.
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function createMockAssessmentNode($state, array $values = [], $doNotReferenceUser = FALSE) {
    $assessment = TestSupport::createAssessment();
    TestSupport::populateAllFieldsData($assessment, 1);

    if ($doNotReferenceUser === FALSE) {
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

    try {
      $assessment->save();
      return $this->setAssessmentState($assessment, $state, $values);
    }
    catch (EntityStorageException $e) {
      return NULL;
    }
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

}
