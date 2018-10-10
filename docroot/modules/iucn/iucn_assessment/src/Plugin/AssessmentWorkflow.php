<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Session\AccountInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\node\NodeInterface;

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

    $state = $node->moderation_state->value;
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
      case 'draft':
        // Any coordinator or higher can edit assessments.
        return $account_role_weight <= $coordinator_weight;

      case 'under_evaluation':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'under_assessment':
        // In this state, assessments can only be edited by their assessors.
        return $assessor == $account->id();

      case 'ready_for_review':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'under_review':
        // Assessments can only be edited by their reviewers.
        return in_array($account->id(), $reviewers);

      case 'reviewed':
        // Reviewed assessments can no longer be edited.
        return FALSE;

      case 'under_comparison':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'approved':
        // Assessments can only be edited by their coordinator.
        return $coordinator == $account->id();

      case 'published':
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

    $state = $node->moderation_state->value;
    return $field == 'field_coordinator' && $state == 'draft'
      || $field == 'field_assessor' && $state == 'under_evaluation'
      || $field == 'field_reviewers' && $state == 'ready_for_review';
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
    return $field == 'field_coordinator' && $state == 'under_evaluation'
      || $field == 'field_assessor' && $state == 'under_assessment'
      || $field == 'field_reviewers' && $state == 'under_review';
  }

  /**
   * All the functions that need to be called when an assessment is saved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   */
  public function assessmentPreSave(NodeInterface $node) {
    if ($node->isNewRevision()) {
      return;
    }
    dpm($node->isDefaultRevision());

    $original = $node->original;
    $original_reviewers = $original->get('field_reviewers')->getValue();
    foreach ($original_reviewers as &$reviewer) {
      $reviewer = $reviewer['target_id'];
    }

    $new_reviewers = $node->get('field_reviewers')->getValue();
    foreach ($new_reviewers as &$reviewer) {
      $reviewer = $reviewer['target_id'];
    }

    $added_reviewers = array_diff($new_reviewers, $original_reviewers);
    $deleted_reviewers = array_diff($original_reviewers, $new_reviewers);

    if (empty($added_reviewers) && empty($deleted_reviewers)) {
      return;
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($node->getEntityTypeId());
    /** @var \Drupal\node\NodeInterface $new_revision */
    // Create a revision for each newly added reviewer.
    foreach ($added_reviewers as $added_reviewer) {
      $new_revision = $storage->createRevision($node, FALSE);
      $new_revision->setRevisionCreationTime(time());
      $new_revision->setRevisionUserId($added_reviewer);
      $new_revision->save();
    }

    // Delete all revisions of reviewers no longer assigned on this assessment.
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

}
