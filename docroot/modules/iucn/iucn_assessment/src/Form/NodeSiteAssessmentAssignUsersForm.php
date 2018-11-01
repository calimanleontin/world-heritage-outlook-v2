<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;

class NodeSiteAssessmentAssignUsersForm {

  public static function access(AccountInterface $account, NodeInterface $node) {
    $state = $node->field_state->value;
    switch ($state) {
      case AssessmentWorkflow::STATUS_CREATION:
      case AssessmentWorkflow::STATUS_NEW:
        $access = AccessResult::allowedIfHasPermission($account, 'assign users to assessments');
        break;

      case AssessmentWorkflow::STATUS_UNDER_EVALUATION:
      case AssessmentWorkflow::STATUS_UNDER_ASSESSMENT:
      case AssessmentWorkflow::STATUS_READY_FOR_REVIEW:
        $access = AccessResult::allowedIf($account->hasPermission('edit assessment in any state')
          || $node->field_coordinator->target_id == $account->id());
        break;
    }
    $access->addCacheableDependency($node);
    return $access;
  }

  public static function alter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $formObject->getEntity();
    $state = $node->field_state->value;

    NodeSiteAssessmentForm::hideUnnecessaryFields($form);
    NodeSiteAssessmentForm::addRedirectToAllActions($form);

    $form['field_coordinator']['widget']['#disabled'] = !in_array($state, [
      AssessmentWorkflow::STATUS_CREATION,
      AssessmentWorkflow::STATUS_NEW,
    ]);
    $form['field_assessor']['widget']['#disabled'] = !in_array($state, [
      AssessmentWorkflow::STATUS_CREATION,
      AssessmentWorkflow::STATUS_NEW,
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
    ]);
    $form['field_reviewers']['widget']['#disabled'] = !in_array($state, [
      AssessmentWorkflow::STATUS_CREATION,
      AssessmentWorkflow::STATUS_NEW,
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_REVIEW,
    ]);
  }

}
