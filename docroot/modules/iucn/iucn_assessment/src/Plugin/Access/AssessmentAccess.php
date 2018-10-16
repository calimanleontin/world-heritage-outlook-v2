<?php

namespace Drupal\iucn_assessment\Plugin\Access;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;

class AssessmentAccess {

  /**
   * Custom access check for the site assessment edit route.
   *
   * Assessors and reviewers are only allowed to edit
   * assessments they are assigned to.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\node\NodeInterface $node
   *   The assessment that is being edited.
   * @param int $node_revision
   *   The revision being edited. This is NULL on node edit pages.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Denied or neutral.
   */
  public function assessmentEdit(AccountInterface $account, NodeInterface $node = NULL, $node_revision = NULL) {
    if ($node->bundle() != 'site_assessment') {
      return AccessResult::allowed();
    }

    if ($account->id() == 1) {
      return AccessResult::allowed();
    }

    if (!empty($node_revision)) {
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($node_revision);

      // Only under_review revisions should be editable.
      if ($node->field_state->value != AssessmentWorkflow::STATUS_UNDER_REVIEW) {
        return AccessResult::forbidden();
      }
    }

    if (empty($node)) {
      return AccessResult::allowed();
    }

    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $has_access = $workflow_service->hasAssessmentEditPermission($account, $node);

    if (!$has_access) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
