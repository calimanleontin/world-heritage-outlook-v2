<?php

namespace Drupal\iucn_assessment\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;

/**
 * Publish approved assessments.
 *
 * @Action(
 *   id = "publish_site_assessment",
 *   label = @Translation("Publish approved assessments"),
 *   type = "node",
 *   confirm = TRUE
 * )
 */
class PublishSiteAssessment extends ActionBase {

  public function execute($node = NULL) {
    if (!$node instanceof NodeInterface) {
      return;
    }
    $state = $node->field_state->value;
    $newState = AssessmentWorkflow::STATUS_PUBLISHED;
    \Drupal::service('iucn_assessment.workflow')->createRevision($node, $newState, NULL, "{$state} ({$node->getRevisionId()}) => {$newState}", TRUE);
  }

  public function access($node, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$node instanceof NodeInterface
      || $node->bundle() != 'site_assessment'
      || empty($node->field_state->value)
      || $node->field_state->value != AssessmentWorkflow::STATUS_APPROVED) {
      $access = AccessResult::forbidden();
    }
    else {
      $access = \Drupal::service('iucn_assessment.workflow')->checkAssessmentAccess($node, 'change_state', $account);
    }
    return $return_as_object ? $access : $access->isAllowed();
  }
}
