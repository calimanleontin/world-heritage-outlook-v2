<?php

namespace Drupal\iucn_assessment\Plugin\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
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
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Denied or neutral.
   */
  public function assessmentEdit(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() != 'site_assessment') {
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
