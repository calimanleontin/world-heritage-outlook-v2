<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Session\AccountInterface;
use Drupal\facets\Exception\Exception;
use Drupal\node\Entity\Node;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Service class used to for the workflow functionality.
 */
class AssessmentWorkflow {

  /**
   * This state is usually assigned to assessments with no state.
   *
   * Do not use this.
   */
  const STATUS_CREATION = 'assessment_creation';

  /**
   * New assessment was just created, waiting for coordinator to be assigned.
   */
  const STATUS_NEW = 'assessment_new';

  /**
   * Coordinator is editing, waiting for assessor to be assigned.
   */
  const STATUS_UNDER_EVALUATION = 'assessment_under_evaluation';

  /**
   * Assessor is assigned and can start editing.
   */
  const STATUS_UNDER_ASSESSMENT = 'assessment_under_assessment';

  /**
   * Assessor has finished, Coordinator is reviewing changes and adds reviewers.
   */
  const STATUS_READY_FOR_REVIEW = 'assessment_ready_for_review';

  /**
   * Reviewers start working.
   */
  const STATUS_UNDER_REVIEW = 'assessment_under_review';

  /**
   * When all reviewers are finished.
   */
  const STATUS_FINISHED_REVIEWING = 'assessment_finished_reviewing';

  /**
   * Coordinator starts the review / comparison phase to merge the changes.
   */
  const STATUS_UNDER_COMPARISON = 'assessment_under_comparison';

  /**
   * Coordinator starts reviewing the references.
   */
  const STATUS_REVIEWING_REFERENCES = 'assessment_reviewing_references';

  /**
   * Coordinator has done the comparison and merge phase.
   */
  const STATUS_APPROVED = 'assessment_approved';

  /**
   * Site is published.
   */
  const STATUS_PUBLISHED = 'assessment_published';

  /**
   * Site is unpublished.
   */
  const STATUS_DRAFT = 'assessment_draft';

  /**
   * Check if an user can edit an assessment.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return bool
   *   True if the user can edit the assessment. False otherwise.
   */
  public function hasAssessmentEditPermission(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() != 'site_assessment') {
      return FALSE;
    }

    $state = $node->field_state->value;
    $account_role_weight = RoleHierarchyHelper::getAccountRoleWeight($account);
    $coordinator_weight = Role::load('coordinator')->getWeight();

    // Accounts more powerful than coordinators can edit every assessment.
    if ($account_role_weight < $coordinator_weight) {
      return TRUE;
    }

    $coordinator = $node->get('field_coordinator')->getValue();
    if (!empty($coordinator)) {
      $coordinator = $coordinator[0]['target_id'];
    }
    $assessor = $node->get('field_assessor')->getValue();
    if (!empty($assessor)) {
      $assessor = $assessor[0]['target_id'];
    }
    $reviewers = $node->get('field_reviewers')->getValue();
    if (!empty($reviewers)) {
      $reviewers_array = [];
      foreach ($reviewers as $reviewer) {
        $reviewers_array[] = $reviewer['target_id'];
      }
      $reviewers = $reviewers_array;
    }

    switch ($state) {
      case self::STATUS_CREATION:
      case self::STATUS_NEW:
      case NULL:
        // Any coordinator or higher can edit assessments.
        return $account_role_weight <= $coordinator_weight;

      case self::STATUS_UNDER_EVALUATION:
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case self::STATUS_UNDER_ASSESSMENT:
        // In this state, assessments can only be edited by their assessors.
        return $assessor == $account->id();

      case self::STATUS_READY_FOR_REVIEW:
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case self::STATUS_UNDER_REVIEW:
        // Only coordinators can edit the main revision.
        // Reviewers will be redirected to their revision.
        if ($node->isDefaultRevision()) {
          return $coordinator == $account->id() || in_array($account->id(), $reviewers);
        }
        // Reviewers can edit their respective revisions.
        return $node->getRevisionUser()->id() == $account->id();

      case self::STATUS_FINISHED_REVIEWING:
        // Reviewed assessments can only be edited by the coordinator.
        if ($node->isDefaultRevision()) {
          return $coordinator == $account->id();
        }
        // Reviewers can no longer edit their respective revisions.
        return FALSE;

      // Assessments can only be edited by their coordinator.
      case self::STATUS_UNDER_COMPARISON:
      case self::STATUS_REVIEWING_REFERENCES:
      case self::STATUS_APPROVED:
      case self::STATUS_PUBLISHED:
      case self::STATUS_DRAFT:
        return $coordinator == $account->id();

    }

    return TRUE;
  }

  /**
   * Check if an user field (e.g. assessor) is visible on an assessment.
   *
   * @param string $field
   *   The field id.
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return bool
   *   True if a field is visible for an assessment in a certain state.
   */
  public function isFieldEnabledForAssessment($field, NodeInterface $node) {
    if ($node->bundle() != 'site_assessment') {
      return FALSE;
    }
    $state = $node->field_state->value;
    return ($field == 'field_coordinator' && (in_array($state, [self::STATUS_CREATION, self::STATUS_NEW]) || empty($state)))
      || ($field == 'field_assessor' && $state == self::STATUS_UNDER_EVALUATION)
      || ($field == 'field_reviewers' && ($state == self::STATUS_READY_FOR_REVIEW || $state == self::STATUS_UNDER_REVIEW));
  }

  /**
   * Check if a field is required for an assessment to move to a certain state.
   *
   * @param string $field
   *   The field.
   * @param string $state
   *   The next state (e.g. 'under_assessment').
   *
   * @return bool
   *   True if the field is required.
   */
  public function isFieldRequiredForState($field, $state) {
    return ($field == 'field_coordinator' && $state == self::STATUS_UNDER_EVALUATION)
      || ($field == 'field_assessor' && $state == self::STATUS_UNDER_ASSESSMENT)
      || ($field == 'field_reviewers' && $state == self::STATUS_UNDER_REVIEW);
  }

  /**
   * All the functions that need to be called when an assessment is saved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   */
  public function assessmentPreSave(NodeInterface $node) {
    /** @var \Drupal\node\NodeInterface $original */
    $original = $node->isDefaultRevision() ? $node->original : $this->getAssessmentRevision($node->getLoadedRevisionId());
    $state = $node->field_state->value;
    $original_state = $original->field_state->value;

    // When saving an assessment with no state, we want to set the NEW state.
    if ($this->assessmentHasNoState($node)) {
      $node->get('field_state')->setValue(self::STATUS_NEW);
      return;
    }

    // Ignore new assessments.
    if ($node->isNew() || $state == self::STATUS_NEW) {
      return;
    }

    // Set the original status to new so a proper revision is created.
    if ($this->assessmentHasNoState($original)) {
      $original->get('field_state')->setValue(self::STATUS_NEW);
    }

    if (!$node->isDefaultRevision()) {
      // When a reviewer finishes his revision, check if all other reviewers
      // have marked their revision is done.
      // If so, mark the default revision as done.
      if ($state == self::STATUS_FINISHED_REVIEWING && $original_state == self::STATUS_UNDER_REVIEW) {
        $default_revision = Node::load($node->id());
        if ($this->isAssessmentReviewed($default_revision, $node->getRevisionId())) {
          $default_revision->get('field_state')->setValue(self::STATUS_FINISHED_REVIEWING);
          $default_revision->save();
        }
        // Save the differences on the revision.
        $this->appendDiffToFieldSettings($node, $default_revision);
      }
      // When the draft revision is published,
      // create a new default revision with the published state.
      elseif ($state == self::STATUS_PUBLISHED && $original_state == self::STATUS_DRAFT) {
        $this->createRevision($node, NULL, NULL, self::STATUS_PUBLISHED, TRUE);
      }
      return;
    }

    // Create or remove reviewer revisions.
    if ($state == self::STATUS_UNDER_REVIEW) {
      $added_reviewers = $this->getAddedReviewers($node, $original);
      $removed_reviewers = $this->getRemovedReviewers($node, $original);

      if (!empty($added_reviewers) || !empty($removed_reviewers)) {
        // Create a revision for each newly added reviewer.
        foreach ($added_reviewers as $added_reviewer) {
          $this->createRevisionForReviewer($node, $added_reviewer);
        }

        // Delete revisions of reviewers no longer assigned on this assessment.
        foreach ($removed_reviewers as $removed_reviewer) {
          $this->deleteReviewerRevisions($node, $removed_reviewer);
        }
      }
    }

    // When an assessor finishes, get the diff and save it.
    if ($state == self::STATUS_READY_FOR_REVIEW && $original_state == self::STATUS_UNDER_ASSESSMENT) {
      $under_evaluation_revision = self::getRevisionByState($node, self::STATUS_UNDER_EVALUATION);
      $this->appendDiffToFieldSettings($node, $under_evaluation_revision, FALSE);
    }

    // Check if the state was changed.
    if ($original_state != $state) {
      // When using $node->setNewRevision(), editing paragraphs makes
      // the changes visible in all revisions.
      // @todo: check why this is happening.
      $revision_state = $original_state;
      $is_unpublished = NULL;
      if ($state == self::STATUS_DRAFT && $original_state == self::STATUS_PUBLISHED) {
        $state = $node->get('field_state')->setValue(self::STATUS_PUBLISHED);
        $revision_state = self::STATUS_DRAFT;
      }
      $this->createRevision($node, NULL, NULL, $revision_state);
      $revision_message = 'State: ' . $node->field_state->value;
      $node->setRevisionLogMessage($revision_message);
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
   *   The modified node.
   * @param \Drupal\node\NodeInterface $compare
   *   The older revision.
   * @param bool $save
   *   Is node->save called.
   */
  public function appendDiffToFieldSettings(NodeInterface $node, NodeInterface $compare, $save = TRUE) {
    $diff = \Drupal::service('iucn_diff_revisions.diff_controller')->compareRevisions($compare->getRevisionId(), $node->getRevisionId());
    $field_settings_json = $node->get('field_settings')->getValue();
    $field_settings = json_decode($field_settings_json, TRUE);
    $field_settings['diff'] = $diff;
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
      $uid = \Drupal::currentUser()->id();
    }
    if (empty($state)) {
      $state = $node->field_state->value;
    }
    if (empty($message)) {
      $message = 'State: ' . $state;
    }
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($node->getEntityTypeId());

    /** @var \Drupal\node\NodeInterface $new_revision */
    $new_revision = $storage->createRevision($node, $is_default);
    $new_revision->setRevisionCreationTime(time());
    $new_revision->setRevisionLogMessage($message);
    $new_revision->setRevisionUserId($uid);
    if (empty($is_published)) {
      $new_revision->setPublished(FALSE);
    }
    $new_revision->get('field_state')->setValue($state);
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

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
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
    if (empty($reviewers)) {
      return [];
    }

    foreach ($reviewers as &$reviewer) {
      $reviewer = $reviewer['target_id'];
    }

    return $reviewers;
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
      \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($reviewer_revision->getRevisionId());
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
    $reviewers = $this->getReviewersArray($node);
    if (!in_array($uid, $reviewers)) {
      return NULL;
    }
    $reviewer_revisions = $this->getReviewerRevisions($node);
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
  public function getReviewerRevisions(NodeInterface $node) {
    if ($node->field_state->value != self::STATUS_UNDER_REVIEW) {
      return [];
    }
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $assessment_revisions_ids = $node_storage->revisionIds($node);
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
   * @return array
   *   The unfinished revisions.
   */
  public function getUnfinishedReviewerRevisions(NodeInterface $node) {
    $unfinished = [];
    $revisions = $this->getReviewerRevisions($node);
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
   *
   * @throws \Exception
   *   Use this function when the default revision is under review.
   */
  public function isReviewerRevision(NodeInterface $node) {
    $original = Node::load($node->id());
    if ($original->field_state->value != self::STATUS_UNDER_REVIEW) {
      throw new \Exception('Default revision is not under review.');
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
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $assessment_revisions_ids = $node_storage->revisionIds($node);
    $assessment_revisions_ids = array_reverse($assessment_revisions_ids);
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
   * DO NOT use this in a presave hook, because it calls $node->save().
   *
   * @param \Drupal\node\NodeInterface $assessment
   *   The assessment.
   * @param string $new_state
   *   The new state id.
   */
  public function forceAssessmentState(NodeInterface $assessment, $new_state) {
    $field_name = 'field_state';
    $old_sid = WorkflowManager::getPreviousStateId($assessment, 'field_state');
    $user = \Drupal::currentUser();
    $user_id = !empty($user) ? $user->id() : 1;
    $transition = WorkflowTransition::create([$old_sid, 'field_name' => $field_name]);
    $transition->setValues($new_state, $user_id, \Drupal::time()->getRequestTime(), '', TRUE);
    $transition->setTargetEntity($assessment);
    $transition->executeAndUpdateEntity(TRUE);
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
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The revision.
   */
  public function getAssessmentRevision($vid) {
    $node_revision = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($vid);
    return $node_revision;
  }

  /**
   * Checks if an assessment is editable.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   *
   * @return bool
   *   Whether or not the assessment is editable.
   */
  public function isAssessmentEditable(NodeInterface $node) {
    return !($node->isDefaultRevision() && $node->field_state->value == self::STATUS_UNDER_REVIEW
      || $node->field_state->value == self::STATUS_PUBLISHED);
  }

}
