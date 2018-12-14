<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;

class NodeSiteAssessmentStateChangeForm {

  public static function alter(&$form, FormStateInterface $form_state) {
    self::addWarning($form, t('You may no longer be able to edit the assessment if the state is changed.'));

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;
    $currentUser = \Drupal::currentUser();

    NodeSiteAssessmentForm::hideUnnecessaryFields($form);
    NodeSiteAssessmentForm::addRedirectToAllActions($form);

    // Hide state change scheduling.
    if (!empty($form['field_state']['widget'][0]['workflow_scheduling'])) {
      $form['field_state']['widget'][0]['workflow_scheduling']['#access'] = FALSE;
    }

    // Hide the save button for every state except under_review.
    // When under review, the save button is useful
    // for adding/removing reviewers.
    if (!$node->isDefaultRevision() || $state != AssessmentWorkflow::STATUS_UNDER_REVIEW) {
      $form['actions']['submit']['#access'] = FALSE;
      $form['actions']['workflow_' . $state]['#access'] = FALSE;
    }

    if (in_array('coordinator', $currentUser->getRoles())
      && $currentUser->hasPermission('assign any coordinator to assessment') === FALSE) {
      $form['field_coordinator']['widget']['#options'] = [
        '_none' => t('- Select -'),
        $currentUser->id() => $currentUser->getAccountName(),
      ];
      $form['field_coordinator']['widget']['#default_value'] = $currentUser->id();
    }

    if ($currentUser->hasPermission('assign users to assessments')) {
      $form['field_coordinator']['#access'] = $form['field_coordinator']['widget']['#required'] = in_array($state, [NULL, AssessmentWorkflow::STATUS_CREATION, AssessmentWorkflow::STATUS_NEW]);
      $form['field_assessor']['#access'] = $form['field_assessor']['widget']['#required'] = $state == AssessmentWorkflow::STATUS_UNDER_EVALUATION;
      $form['field_reviewers']['#access'] = $form['field_reviewers']['widget']['#required'] = in_array($state, [AssessmentWorkflow::STATUS_READY_FOR_REVIEW, AssessmentWorkflow::STATUS_UNDER_REVIEW]);
    }
    else {
      $form['field_coordinator']['#access'] = FALSE;
      $form['field_assessor']['#access'] = FALSE;
      $form['field_reviewers']['#access'] = FALSE;
    }

    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT
      && $node->field_assessor->target_id == $currentUser->id()
      && !self::assessmentHasNewReferences($node)) {

      self::addWarning($form, t("You have not added any new references. Are you sure you haven't forgotten any references?"));
    }
  }

  public static function assessmentHasNewReferences(NodeInterface $node) {
    $old_assessment = \Drupal::service('iucn_assessment.workflow')->getRevisionByState($node, AssessmentWorkflow::STATUS_NEW);
    $old_references = $old_assessment->field_as_references_p->getValue();
    $new_references = $node->field_as_references_p->getValue();
    if (empty($new_references)) {
      return FALSE;
    }
    else {
      $old_references = !empty($old_references) ? array_column($old_references, 'target_id') : [];
      $new_references = array_column($new_references, 'target_id');
      $added_references = array_diff($new_references, $old_references);
      if (empty($added_references)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  public static function addWarning(&$form, $message) {
    if (empty($form['warning'])) {
      $form['warning'] = [];
    }
    $form['warning'][] = [
      '#type' => 'markup',
      '#markup' => sprintf('<div role="contentinfo" aria-label="Warning message" class="messages messages--warning">%s</div>',
        $message),
      '#weight' => -1000,
    ];
  }

}
