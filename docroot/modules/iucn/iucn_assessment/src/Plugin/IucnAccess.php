<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Session\AccountInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\node\NodeInterface;

/**
 * Service class used to assess an user's access on certain parts of the site.
 */
class IucnAccess {

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
      case 'new':
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
  }

}
