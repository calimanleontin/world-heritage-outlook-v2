<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Service class used to assess an user's access on certain parts of the site.
 */
class AssessmentWorkflow {

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
      case 'assessment_new':
        // Any coordinator or higher can edit assessments.
        return $account_role_weight <= $coordinator_weight;

      case 'assessment_under_evaluation':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'assessment_under_assessment':
        // In this state, assessments can only be edited by their assessors.
        return $assessor == $account->id();

      case 'assessment_ready_for_review':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'assessment_under_review':
        // Only coordinators can edit the main revision.
        if ($node->isDefaultRevision()) {
          return $coordinator == $account->id();
        }
        // Reviewers can edit their respective revisions.
        return $node->getRevisionUser()->id() == $account->id();

      case 'assessment_reviewed':
        // Reviewed assessments can no longer be edited.
        return FALSE;

      case 'assessment_under_comparison':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'assessment_approved':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'assessment_published':
        // After being published, assessments can only be edited by admins.
        return $account_role_weight < $coordinator_weight;
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
    return $field == 'field_coordinator' && $state == 'assessment_new'
      || $field == 'field_assessor' && $state == 'assessment_under_evaluation'
      || $field == 'field_reviewers' && $state == 'assessment_ready_for_review';
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
    return $field == 'field_coordinator' && $state == 'assessment_under_evaluation'
      || $field == 'field_assessor' && $state == 'assessment_under_assessment'
      || $field == 'field_reviewers' && $state == 'assessment_under_review';
  }

  /**
   * All the functions that need to be called when an assessment is saved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   */
  public function assessmentPreSave(NodeInterface $node) {
    if (!$node->isDefaultRevision()) {
      $default_revision = Node::load($node->id());
      if ($this->isAssessmentReviewed($default_revision, $node->getRevisionId())) {
        $default_revision->field_state->value = 'assessment_finished_reviewing';
        $default_revision->save();
      }
      return;
    }

    if ($node->isNew()) {
      return;
    }

    $create_revision = FALSE;
    $revision_message = '';

    $original = $node->original;
    $original_reviewers = $original->get('field_reviewers')->getValue();
    foreach ($original_reviewers as &$reviewer) {
      $reviewer = $reviewer['target_id'];
    }

    $state = $node->field_state->value;

    if ($original->field_state->value != $state) {
      $create_revision = TRUE;
      $revision_message .= 'State changed: ' . $original->field_state->value . ' -> ' . $node->field_state->value . '. ';
    }

    $new_reviewers = $node->get('field_reviewers')->getValue();
    foreach ($new_reviewers as &$reviewer) {
      $reviewer = $reviewer['target_id'];
    }

    $added_reviewers = array_diff($new_reviewers, $original_reviewers);
    $deleted_reviewers = array_diff($original_reviewers, $new_reviewers);

    if (!empty($added_reviewers) || !empty($deleted_reviewers)) {
      // We need to create a new revision when creating revisions for reviewers.
      $create_revision = TRUE;
      $revision_message .= 'Reviewers field changed. ';

      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage($node->getEntityTypeId());
      /** @var \Drupal\node\NodeInterface $new_revision */
      // Create a revision for each newly added reviewer.
      foreach ($added_reviewers as $added_reviewer) {
        $new_revision = $storage->createRevision($node, FALSE);
        $new_revision->setRevisionCreationTime(time());
        $revision_user = User::load($added_reviewer)->getUsername();
        $new_revision->setRevisionLogMessage('Revision created for reviewer ' . $revision_user);
        $new_revision->setRevisionUserId($added_reviewer);
        $new_revision->save();
      }

      // Delete revisions of reviewers no longer assigned on this assessment.
      $assessment_revisions_ids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);
      foreach ($assessment_revisions_ids as $rid) {
        $node_revision = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadRevision($rid);
        if (in_array($node_revision->getRevisionUserId(), $deleted_reviewers) && !$node_revision->isDefaultRevision()) {
          \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($rid);
        }
      }
    }

    if ($create_revision) {
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime(time());
      $node->setRevisionUserId(\Drupal::currentUser()->id());
      $node->setRevisionLogMessage($revision_message);
    }

    if ($state = 'assessment_published') {
      $node->setPublished(TRUE);
    }
    else {
      $node->setPublished(FALSE);
    }

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

    $revision_ids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);
    foreach ($revision_ids as $revision_id) {
      $node_revision = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($revision_id);

      if (!empty($last_review)  && $revision_id == $last_review) {
        continue;
      }

      if (!$node_revision->isDefaultRevision() && $node_revision->field_state->value == 'assessment_under_review') {
        return FALSE;
      }
    }

    return TRUE;
  }

}
