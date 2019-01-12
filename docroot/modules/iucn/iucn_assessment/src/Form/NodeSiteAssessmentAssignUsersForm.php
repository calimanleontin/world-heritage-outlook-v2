<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;

class NodeSiteAssessmentAssignUsersForm {

  use AssessmentEntityFormTrait;

  public static function access(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() != 'site_assessment') {
      $access = AccessResult::forbidden();
    }
    else {
      $state = $node->field_state->value;
      $isUserAssignmentState = !empty($node->field_state->value) && in_array($state, AssessmentWorkflow::USER_ASSIGNMENT_STATES);
      $coordinator = !empty($node->field_coordinator->target_id) ? $node->field_coordinator->target_id : -1;
      if ($isUserAssignmentState == FALSE) {
        $access = AccessResult::forbidden();
      }
      else {
        $access = !empty($coordinator)
          ? AccessResult::allowedIf($account->hasPermission('edit assessment in any state')
            || ($coordinator == $account->id() && $state != AssessmentWorkflow::STATUS_NEW))
          : AccessResult::allowedIfHasPermission($account, 'assign users to assessments');
      }
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

    self::hideUnnecessaryFields($form);
    self::addRedirectToAllActions($form);

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

    $form['actions']['submit']['#submit'][] = [self::class, 'submitForm'];
  }

  public static function submitForm(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $formObject->getEntity();
    $state = $node->field_state->value;
    if (in_array($state, [
      AssessmentWorkflow::STATUS_CREATION,
      AssessmentWorkflow::STATUS_NEW,
    ]) && !empty($node->field_coordinator->getValue())) {
      // If the coordinator was set, set assessment status to UNDER EVALUATION.
      \Drupal::service('iucn_assessment.workflow')->forceAssessmentState($node, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    }
  }

}
