<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\iucn_assessment\Controller\DiffController;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Service class used to for the workflow functionality.
 */
class AssessmentWorkflow {

  /** This state is usually assigned to assessments with no state */
  const STATUS_CREATION = 'assessment_creation';

  /** New assessment was just created, waiting for coordinator to be assigned */
  const STATUS_NEW = 'assessment_new';

  /** Coordinator is editing, waiting for assessor to be assigned */
  const STATUS_UNDER_EVALUATION = 'assessment_under_evaluation';

  /** Assessor is assigned and can start editing */
  const STATUS_UNDER_ASSESSMENT = 'assessment_under_assessment';

  /** Assessor has finished, Coordinator is reviewing changes and adds reviewers */
  const STATUS_READY_FOR_REVIEW = 'assessment_ready_for_review';

  /** Reviewers start working */
  const STATUS_UNDER_REVIEW = 'assessment_under_review';

  /** When all reviewers are finished */
  const STATUS_FINISHED_REVIEWING = 'assessment_finished_reviewing';

  /** Coordinator starts the review / comparison phase to merge the changes */
  const STATUS_UNDER_COMPARISON = 'assessment_under_comparison';

  /** Coordinator starts reviewing the references */
  const STATUS_REVIEWING_REFERENCES = 'assessment_reviewing_references';

  /** Coordinator has done the comparison and merge phase */
  const STATUS_APPROVED = 'assessment_approved';

  /** Assessment is published */
  const STATUS_PUBLISHED = 'assessment_published';

  /** Assessment is unpublished */
  const STATUS_DRAFT = 'assessment_draft';

  /**
   * If node has one of the following states, coordinators/assessors/reviewers
   * can be assigned.
   */
  const USER_ASSIGNMENT_STATES = [
    self::STATUS_CREATION,
    self::STATUS_NEW,
    self::STATUS_UNDER_EVALUATION,
    self::STATUS_UNDER_ASSESSMENT,
    self::STATUS_READY_FOR_REVIEW,
    self::STATUS_UNDER_REVIEW,
  ];

  const DIFF_STATES = [
    self::STATUS_READY_FOR_REVIEW,
    self::STATUS_UNDER_COMPARISON,
  ];

  const CURRENT_WORKFLOW_CYCLE_STATE_KEY = 'iucn_assessment_current_workflow_cycle_state';

  /** @var \Drupal\Core\Session\AccountProxyInterface */
  protected $currentUser;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\taxonomy\TermStorageInterface */
  protected $termStorage;

  /** @var \Drupal\iucn_assessment\Controller\DiffController */
  protected $diffController;

  public function __construct(AccountProxyInterface $currentUser, EntityTypeManagerInterface $entityTypeManager, DiffController $diffController) {
    $this->currentUser = $currentUser;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->diffController = $diffController;
  }

  /**
   * Check assessment node view/edit acccess.
   *
   * @param \Drupal\node\NodeInterface $node
   * @param string $action
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function checkAssessmentAccess(NodeInterface $node, $action = 'edit', AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = $this->currentUser;
    }
    if ($node->bundle() != 'site_assessment') {
      return AccessResult::allowed();
    }
    $access = AccessResult::neutral();
    $state = $node->field_state->value ?: self::STATUS_CREATION;
    $accountIsCoordinator = $node->field_coordinator->target_id === $account->id();
    $accountIsAssessor = $node->field_assessor->target_id === $account->id();

    if ($account->hasPermission('edit assessment in any state')) {
      return AccessResult::allowed();
    }

    if ($action == 'edit') {
      switch ($state) {
        case self::STATUS_CREATION:
        case self::STATUS_NEW:
          $access = AccessResult::allowedIfHasPermission($account, 'edit new assessments');
          break;

        case self::STATUS_UNDER_EVALUATION:
        case self::STATUS_READY_FOR_REVIEW:
        case self::STATUS_UNDER_COMPARISON:
        case self::STATUS_REVIEWING_REFERENCES:
        case self::STATUS_APPROVED:
        case self::STATUS_PUBLISHED:
        case self::STATUS_DRAFT:
          // Assessments can only be edited by their coordinator.
          $access = AccessResult::allowedIf($accountIsCoordinator);
          break;


        case self::STATUS_UNDER_ASSESSMENT:
          // In this state, assessments can only be edited by their assessors.
          $access = AccessResult::allowedIf($accountIsAssessor);
          break;

        case self::STATUS_UNDER_REVIEW:
          // Only coordinators can edit the main revision.
          // Reviewers can edit their respective revisions.
          $access = AccessResult::allowedIf($node->getRevisionUserId() === $account->id());
          break;

        case self::STATUS_FINISHED_REVIEWING:
          // Coordinators must move to UNDER_COMPARISON before editing.
          $access = AccessResult::forbidden();
          break;

        default:
          $access = AccessResult::forbidden();
      }
    }
    elseif ($action == 'change_state') {
      switch ($state) {
        case self::STATUS_CREATION:
        case self::STATUS_NEW:
          $access = AccessResult::allowedIfHasPermission($account, 'assign coordinator to assessment');
          break;

        case self::STATUS_FINISHED_REVIEWING:
          $access = AccessResult::allowedIf($accountIsCoordinator);
          break;

        default:
          return $this->checkAssessmentAccess($node, 'edit', $account);
      }
    }
    $access->addCacheableDependency($node);
    return $access;
  }

  /**
   * Gets the previous revision from the workflow - the revision from witch the
   * actual one started.
   *
   * @param \Drupal\node\NodeInterface $revision
   *
   * @return \Drupal\node\NodeInterface|null
   */
  public function getPreviousWorkflowRevision(NodeInterface $revision) {
    $workflow = [
      self::STATUS_CREATION,
      self::STATUS_NEW,
      self::STATUS_UNDER_EVALUATION,
      self::STATUS_UNDER_ASSESSMENT,
      self::STATUS_READY_FOR_REVIEW,
      self::STATUS_UNDER_REVIEW,
      self::STATUS_FINISHED_REVIEWING,
      self::STATUS_UNDER_COMPARISON,
      self::STATUS_REVIEWING_REFERENCES,
      self::STATUS_APPROVED,
      self::STATUS_PUBLISHED,
    ];
    $currentState = !empty($revision->field_state->value)
      ? $revision->field_state->value
      : self::STATUS_NEW;

    if (in_array($currentState, [self::STATUS_FINISHED_REVIEWING, self::STATUS_UNDER_COMPARISON])) {
      // Except the default revisions, there are multiple revisions with
      // "Under review" and "Finished reviewing" status (one for each reviewer),
      // so we compare the "Finished reviewing" and "Under comparison" revisions
      // with the only "Ready for review" one.
      $previousStateKey = array_search(self::STATUS_READY_FOR_REVIEW, $workflow);
    }
    else {
      $previousStateKey = array_search($currentState, $workflow) - 1;
    }
    $previousState = !empty($workflow[$previousStateKey]) ? $workflow[$previousStateKey] : NULL;
    return !empty($previousState) ? $this->getRevisionByState($revision, $previousState) : NULL;
  }

  /**
   * All the functions that need to be called when an assessment is saved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   */
  public function assessmentPreSave(NodeInterface $node) {
    if ($node->isNew() || $this->assessmentHasNoState($node)) {
      // Ignore new assessments.
      $this->createSiteProtectionParagraphs($node);
      $this->forceAssessmentState($node, 'assessment_new', FALSE);
      return;
    }

    $state = $node->field_state->value;
    /** @var \Drupal\node\NodeInterface $original */
    $original = $node->isDefaultRevision() ? $node->original : $this->getAssessmentRevision($node->getLoadedRevisionId());
    $original_state = $original->field_state->value;

    // Block only for reviewers' revision:
    // Sets the node default revision status to STATUS_FINISHED_REVIEWING when the last reviewer marks revision as done.
    // Creates a new revision.
    if (!$node->isDefaultRevision()) {
      // IMPORTANT: when programatically saving a revision
      // field_state->workflow_transition is empty.
      // When the transition is empty, the workflow module automatically creates
      // a transition, but incorrectly calculates the from_state and to_state
      // for revisions.
      // We need to call our function that correctly populates the transition.
      $this->forceAssessmentState($node, $node->field_state->value, FALSE);

      // When a reviewer finishes his revision, check if all other reviewers
      // have marked their revision is done.
      // If so, mark the default revision as done.
      if ($state == self::STATUS_FINISHED_REVIEWING && $original_state == self::STATUS_UNDER_REVIEW) {
        $default_revision = Node::load($node->id());
        $original_revision = self::getRevisionByState($node, self::STATUS_READY_FOR_REVIEW);
        $this->appendCommentsToFieldSettings($default_revision, $node, FALSE);
        if ($this->isAssessmentReviewed($default_revision, $node->getRevisionId())) {
          $this->forceAssessmentState($default_revision, self::STATUS_FINISHED_REVIEWING, FALSE);
          $default_revision->isDefaultRevision(TRUE);
        }
        // Save the differences on the revision.
        $this->appendDiffToFieldSettings($default_revision, $original_revision->getRevisionId(), $node->getRevisionId());
        $default_revision->save();
      }
      // When the draft revision is published,
      // create a new default revision with the published state.
      elseif ($state == self::STATUS_PUBLISHED && $original_state == self::STATUS_DRAFT) {
        $this->createRevision($node, NULL, NULL, self::STATUS_PUBLISHED, TRUE);
      }
      return;
    }

    $this->setCoordinatorAndState($node, FALSE);

    // Programmatically set values on fields
    // @see: https://helpdesk.eaudeweb.ro/issues/5685
    if ($this->isNewAssessment($original) && $state == self::STATUS_UNDER_EVALUATION) {
      $node->field_as_start_date->value = date(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    }
    if ($original_state == self::STATUS_REVIEWING_REFERENCES && $state == self::STATUS_APPROVED) {
      $node->field_as_end_date->value = date(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    }
    if (in_array($original_state, [self::STATUS_REVIEWING_REFERENCES, self::STATUS_DRAFT]) && $state == self::STATUS_PUBLISHED) {
      $node->field_date_published->value = date(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    }

    // Create or remove reviewer revisions.
    if ($state == self::STATUS_UNDER_REVIEW) {
      if ($original_state == self::STATUS_UNDER_REVIEW) {
        $added_reviewers = $this->getAddedReviewers($node, $original);
        $removed_reviewers = $this->getRemovedReviewers($node, $original);
      }
      else {
        $added_reviewers = $this->getReviewersArray($node);
        $removed_reviewers = NULL;
      }

      // Create a revision for each newly added reviewer.
      if (!empty($added_reviewers)) {
        foreach ($added_reviewers as $added_reviewer) {
          if (empty($this->getReviewerRevision($node, $added_reviewer))) {
            $this->createRevisionForReviewer($node, $added_reviewer);
          }
        }
      }
      // Delete revisions of reviewers no longer assigned on this assessment.
      if (!empty($removed_reviewers)) {
        foreach ($removed_reviewers as $removed_reviewer) {
          $this->deleteReviewerRevisions($node, $removed_reviewer);
        }
      }
      $unfinished_reviews = $this->getUnfinishedReviewerRevisions($node);
      if (empty($unfinished_reviews)) {
        $node->field_state->value = self::STATUS_FINISHED_REVIEWING;
      }
    }

    // When an assessor finishes, get the diff and save it.
    if ($state == self::STATUS_READY_FOR_REVIEW && $original_state == self::STATUS_UNDER_ASSESSMENT) {
      $under_evaluation_revision = self::getRevisionByState($node, self::STATUS_UNDER_EVALUATION);
      $this->appendDiffToFieldSettings($node, $under_evaluation_revision->getRevisionId(), $original->getRevisionId());
    }

    // After leaving the ready for review state, we no longer need the diff.
    if ($state != $original_state && $original_state == self::STATUS_READY_FOR_REVIEW) {
      $this->clearKeyFromFieldSettings($node, 'diff');
      $node->save();
    }

    // Check if the state was changed.
    if ($original_state != $state) {
      // When using $node->setNewRevision(), editing paragraphs makes
      // the changes visible in all revisions.
      // @todo: check why this is happening.
      $revision_state = $original_state;

      // Set the original status to new so a proper revision is created.
      if ($this->assessmentHasNoState($original)) {
        $revision_state = self::STATUS_NEW;
      }

      $is_unpublished = NULL;
      if ($state == self::STATUS_DRAFT && $original_state == self::STATUS_PUBLISHED) {
        $this->forceAssessmentState($node, self::STATUS_PUBLISHED, FALSE);
        $revision_state = self::STATUS_DRAFT;
      }
      $this->createRevision($node, NULL, NULL, $revision_state);
      $node->setRevisionLogMessage("{$original_state} => {$state}");
    }

    if ($state == self::STATUS_PUBLISHED) {
      $node->setPublished(TRUE);
    }
    else {
      $node->setPublished(FALSE);
    }

    $node->setRevisionCreationTime(time());
  }

  /**
   * Save the diff between 2 revisions.
   *
   * This function gets the differences between two nodes and
   * appends it to the field settings of the first node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that will store the json.
   * @param \Drupal\node\NodeInterface $compare
   *   The node to compare
   * @param bool $save
   *   Is node->save called.
   * @param bool $reverse_comparison
   *   Reverse comparison.
   */
  public function appendDiffToFieldSettings(NodeInterface $node, $original_vid, $comparing_vid) {
    $diff = $this->diffController->compareRevisions($original_vid, $comparing_vid);
    $field_settings_json = $node->field_settings->value;
    $field_settings = json_decode($field_settings_json, TRUE);
    if (empty($field_settings['diff'])) {
      $field_settings['diff'] = [];
    }
    if (!empty($field_settings['comments'])) {
      $reviewers = $this->getReviewersArray($node);
      foreach (array_keys($field_settings['comments']) as $tab) {
        if ($node->field_state->value != self::STATUS_READY_FOR_REVIEW) {
          // Assessor comments are still stored, but we don't want to highlight tabs
          // that only have assessor comments. In this state, we are going to
          // highlight tabs that have at least one reviewer comment.
          if (!empty(array_intersect(array_keys($field_settings['comments'][$tab]), $reviewers))) {
            $diff['fieldgroups'][$tab] = $tab;
          }
        }
        else {
          $diff['fieldgroups'][$tab] = $tab;
        }
      }
    }
    $field_settings['diff'][$comparing_vid] = $diff;
    $field_settings_json = json_encode($field_settings);
    $node->get('field_settings')->setValue($field_settings_json);
  }

  /**
   * Adds the comments from a reviewer revision to the main revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The default revision.
   * @param \Drupal\node\NodeInterface $revision
   *   The reviewer revision.
   * @param bool $save
   *   Is node->save called.
   */
  public function appendCommentsToFieldSettings(NodeInterface $node, NodeInterface $revision, $save = TRUE) {
    $revision_field_settings_json = $revision->field_settings->value;
    $revision_field_settings = json_decode($revision_field_settings_json, TRUE);
    if (empty($revision_field_settings['comments'])) {
      return;
    }

    $field_settings_json = $node->field_settings->value;
    $field_settings = json_decode($field_settings_json, TRUE);

    foreach ($revision_field_settings['comments'] as $tab => $revision_comment) {
      if (!empty($revision_comment[$revision->getRevisionUserId()])) {
        $field_settings['comments'][$tab][$revision->getRevisionUserId()] = $revision_comment[$revision->getRevisionUserId()];
      }
    }
    $field_settings_json = json_encode($field_settings);
    $node->get('field_settings')->setValue($field_settings_json);
    if ($save) {
      $node->save();
    }
  }

  /**
   * Create a revision for an assessment.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $uid
   *   The user id of the reviewer.
   * @param string $message
   *   The revision log message.
   * @param bool $state
   *   If this is set, the revision will have a certain state.
   * @param bool $is_default
   *   Whether or not the created revision is the default one.
   */
  public function createRevision(NodeInterface $node, $uid = NULL, $message = '', $state = NULL, $is_default = FALSE) {
    if (empty($uid)) {
      $uid = $this->currentUser->id();
    }
    if (empty($state)) {
      $state = $node->field_state->value;
    }
    if (empty($message)) {
      $message = 'State: ' . $state;
    }
    /** @var \Drupal\node\NodeInterface $new_revision */
    $new_revision = $this->nodeStorage->createRevision($node, $is_default);
    $new_revision->setRevisionCreationTime(time());
    $new_revision->setRevisionLogMessage($message);
    $new_revision->setRevisionUserId($uid);
    $new_revision->setPublished(FALSE);
    $this->forceAssessmentState($new_revision, $state, FALSE);
    $new_revision->save();
  }

  /**
   * Creates a revision for a reviewer.
   *
   * This function sets the appropiate revision user id and message when
   * creating a revision for a reviewer.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $uid
   *   The user id of the reviewer.
   */
  public function createRevisionForReviewer(NodeInterface $node, $uid) {
    $revision_user = User::load($uid)->getUsername();
    $message = 'Revision created for reviewer ' . $revision_user;
    $this->createRevision($node, $uid, $message);
  }

  /**
   * Check if an assessment is reviewed.
   *
   * An assessment is considered reviewed if there are no revisions
   * with the state Under review, apart from the default one.
   *
   * If the $last_review parameter is set, this function will check if
   * all the reviews apart from $last_review are marked as Done.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $last_review
   *   The last review id.
   *
   * @return bool
   *   True if an assessment is reviewed, false otherwise.
   */
  public function isAssessmentReviewed(NodeInterface $node, $last_review = NULL) {
    if ($node->bundle() != 'site_assessment') {
      return FALSE;
    }
    $unfinished_revisions = $this->getUnfinishedReviewerRevisions($node);
    if (count($unfinished_revisions) > 1) {
      return FALSE;
    }
    if (empty($unfinished_revisions)) {
      return TRUE;
    }
    /** @var \Drupal\node\NodeInterface $unfinished_revision */
    $unfinished_revision = reset($unfinished_revisions);
    if ($unfinished_revision->getRevisionId() == $last_review) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Retrieve the user ids of all reviewers added on a node save.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The saved node.
   * @param \Drupal\node\NodeInterface $original
   *   The node before the save.
   *
   * @return array
   *   An array with all the user ids.
   */
  public function getAddedReviewers(NodeInterface $node, NodeInterface $original) {
    $original_reviewers = $this->getReviewersArray($original);
    $new_reviewers = $this->getReviewersArray($node);
    return array_diff($new_reviewers, $original_reviewers);
  }

  /**
   * Retrieve the user ids of all reviewers removed on a node save.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The saved node.
   * @param \Drupal\node\NodeInterface $original
   *   The node before the save.
   *
   * @return array
   *   An array with all the user ids.
   */
  public function getRemovedReviewers(NodeInterface $node, NodeInterface $original) {
    $original_reviewers = $this->getReviewersArray($original);
    $new_reviewers = $this->getReviewersArray($node);
    return array_diff($original_reviewers, $new_reviewers);
  }

  /**
   * Returns an array containing the user ids of all the reviewers.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return array
   *   The reviewers in field_reviewers.
   */
  public function getReviewersArray(NodeInterface $node) {
    $reviewers = $node->get('field_reviewers')->getValue();
    if (!empty($reviewers)) {
      return array_column($reviewers, 'target_id');
    }
    return [];
  }

  /**
   * Deletes the revision created for a reviewer.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $uid
   *   The user id of the reviewer.
   */
  public function deleteReviewerRevisions(NodeInterface $node, $uid) {
    $reviewer_revision = $this->getReviewerRevision($node, $uid);
    if (!empty($reviewer_revision)) {
      $this->nodeStorage->deleteRevision($reviewer_revision->getRevisionId());
    }
  }

  /**
   * Retrieves the revision created for a reviewer.
   *
   * All the reviewer revisions have their revision user id set
   * equal to the uid of the reviewer.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $uid
   *   The user id of the reviewer.
   *
   * @return \Drupal\node\NodeInterface
   *   The revision.
   */
  public function getReviewerRevision(NodeInterface $node, $uid) {
    $reviewer_revisions = $this->getAllReviewersRevisions($node);
    foreach ($reviewer_revisions as $node_revision) {
      /** @var \Drupal\node\Entity\Node $node_revision */
      if ($node_revision->getRevisionUserId() == $uid && !$node_revision->isDefaultRevision()) {
        return $node_revision;
      }
    }
    return NULL;
  }

  /**
   * Returns all the reviewer revisions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return array
   *   The revisions.
   */
  public function getAllReviewersRevisions(NodeInterface $node) {
    $assessment_revisions_ids = $this->nodeStorage->revisionIds($node);
    $revisions = [];
    foreach ($assessment_revisions_ids as $rid) {
      /** @var \Drupal\node\Entity\Node $node_revision */
      $node_revision = $this->getAssessmentRevision($rid);
      if ($this->isReviewerRevision($node_revision)) {
        $revisions[] = $node_revision;
      }
    }
    return $revisions;
  }

  /**
   * Get an array of unfinished reviewer revisions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return NodeInterface[]
   *   The unfinished revisions.
   */
  public function getUnfinishedReviewerRevisions(NodeInterface $node) {
    $unfinished = [];
    $revisions = $this->getAllReviewersRevisions($node);
    /** @var \Drupal\node\NodeInterface $revision */
    foreach ($revisions as $revision) {
      if ($revision->field_state->value == self::STATUS_UNDER_REVIEW) {
        $unfinished[] = $revision;
      }
    }
    return $unfinished;
  }

  /**
   * Check if a revision is a reviewer revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return bool
   *   True or false.
   */
  public function isReviewerRevision(NodeInterface $node) {
    $reviewers = $this->getReviewersArray($node);
    if (!in_array($node->getRevisionUserId(), $reviewers)) {
      return FALSE;
    }
    return in_array($node->field_state->value, [self::STATUS_UNDER_REVIEW, self::STATUS_FINISHED_REVIEWING]) && !$node->isDefaultRevision();
  }

  /**
   * Gets the latest revision with a certain state.
   *
   * If state is under_revision, reviewer revisions are ignored.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param string $state
   *   The desired state.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A revision or null.
   */
  public function getRevisionByState(NodeInterface $node, $state) {
    $assessment_revisions_ids = array_reverse($this->nodeStorage->revisionIds($node));
    $reviewers = $this->getReviewersArray($node);
    foreach ($assessment_revisions_ids as $rid) {
      /** @var \Drupal\node\Entity\Node $node_revision */
      $node_revision = $this->getAssessmentRevision($rid);

      // We are not interested in reviewer revisions.
      if ($state == self::STATUS_UNDER_REVIEW
        && in_array($reviewers, $node_revision->getRevisionUserId())) {
        continue;
      }

      if ($node_revision->field_state->value == $state) {
        return $node_revision;
      }
    }
    return NULL;
  }

  /**
   * Force a state change on an assessment.
   *
   * If used in a node presave, set $execute = FALSE.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param string $new_state
   *   The new state id.
   * @param bool $execute
   *   If true, node_save will be called on the node.
   *   Do not use in hook_node_presave().
   */
  public function forceAssessmentState(NodeInterface $assessment, $new_state, $execute = TRUE) {
    $old_sid = WorkflowManager::getPreviousStateId($assessment, 'field_state');
    if ($old_sid == self::STATUS_CREATION) {
      $old_sid = self::STATUS_NEW;
    }
    $transition = WorkflowTransition::create([$old_sid, 'field_name' => 'field_state']);
    $transition->setValues($new_state, $this->currentUser->id() ?: 1, time(), '', TRUE);
    $transition->setTargetEntity($assessment);
    $transition->force(TRUE);
    if ($execute) {
      $transition->executeAndUpdateEntity(TRUE);
    }
    else {
      $assessment->field_state->value = $new_state;
      $assessment->field_state->workflow_transition = $transition;
    }
  }

  /**
   * Check if an assessment has either no state or the creation state.
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   *
   * @return bool
   *   True or false.
   */
  public function assessmentHasNoState(NodeInterface $assessment) {
    return $assessment->field_state->value == self::STATUS_CREATION
      || empty($assessment->field_state->value);
  }

  /**
   * Get an assessment revision by its vid.
   *
   * @param int $vid
   *   The revision id.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The revision.
   */
  public function getAssessmentRevision($vid) {
    return $this->nodeStorage->loadRevision($vid);
  }

  /**
   * Clear a key from an assessment's field settings.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param string $key
   *   The key to be deleted.
   */
  public function clearKeyFromFieldSettings(NodeInterface $node, $key) {
    $settings = $node->field_settings->value;
    $settings = json_decode($settings, TRUE);
    if (!empty($settings[$key])) {
      unset($settings[$key]);
    }
    $node->field_settings->value = json_encode($settings);
  }

  /**
   * Check if an assessment is new (has no state or state = NEW)
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @return bool
   *   True if the assessment is new.
   */
  public function isNewAssessment(NodeInterface $node) {
    return $this->assessmentHasNoState($node) || $node->field_state->value == self::STATUS_NEW;
  }

  /**
   * Sets the current user as a coordinator and updates the state.
   *
   * This function should only be used for new assessments. Sets the
   * current user as a coordinator if he has the coordinator role and
   * move the node into the under evaluation state.
   *
   * @param NodeInterface $node
   */
  public function setCoordinatorAndState(NodeInterface $node, $execute = TRUE) {
    if (!$node->isDefaultRevision()) {
      return;
    }
    if (!empty($node->field_coordinator->target_id) || !$this->isNewAssessment($node)) {
      return;
    }
    $current_user = \Drupal::currentUser();
    if (!in_array('coordinator', $current_user->getRoles())) {
      return;
    }

    $node->field_coordinator->target_id = $current_user->id();
    $this->forceAssessmentState($node, AssessmentWorkflow::STATUS_UNDER_EVALUATION, $execute);
  }

  /**
   * Create a site_protection paragraph for each existing term in
   * "assessment_protection_topic" vocabulary if the node field field_as_protection
   * is empty.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSiteProtectionParagraphs(NodeInterface $node) {
    if (empty($node->field_as_protection->getValue())) {
      $terms = $this->termStorage->loadByProperties([
        'vid' => 'assessment_protection_topic',
      ]);
      $protectionParagraphs = [];
      foreach ($terms as $term) {
        $paragraph = Paragraph::create([
          'type' => 'as_site_protection',
          'field_as_protection_topic' => [$term->id()],
        ]);
        $paragraph->save();
        $protectionParagraphs[] = $paragraph;
      }
      $node->set('field_as_protection', $protectionParagraphs);
    }
  }

}
