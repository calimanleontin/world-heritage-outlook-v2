<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;

class NodeSiteAssessmentAssignUsersForm {

  public static function access(AccountInterface $account, NodeInterface $node) {
    return AccessResult::allowedIf(!empty($node->field_state->value) && in_array($node->field_state->value, AssessmentWorkflow::USER_ASSIGNMENT_STATES));
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
