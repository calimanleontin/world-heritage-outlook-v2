<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;

class NodeSiteAssessmentStateChangeForm {

  public static function alter(&$form, FormStateInterface $form_state) {
    // Hide state change scheduling.
    if (!empty($form['field_state']['widget'][0]['workflow_scheduling'])) {
      $form['field_state']['widget'][0]['workflow_scheduling']['#access'] = FALSE;
    }
    $form['#submit'][] = ['Drupal\iucn_assessment\Form\NodeSiteAssessmentStateChangeForm', 'stateChangeSubmitRedirect'];
    foreach ($form['actions'] as $key => &$action) {
      if (strpos($key, 'workflow_') !== FALSE) {
        $action['#submit'][] = ['Drupal\iucn_assessment\Form\NodeSiteAssessmentStateChangeForm', 'stateChangeSubmitRedirect'];
      }
    }
    $form['advanced']['#access'] = FALSE;
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;
    $form['revision']['#access'] = FALSE;
    $form['field_state']['#access'] = FALSE;
    $node = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\node\NodeInterface $node */
    $current_state = $node->field_state->value;
    // Hide the save button for every state except under_review.
    // When under review, the save button is useful
    // for adding/removing reviewers.
    if ($current_state != AssessmentWorkflow::STATUS_UNDER_REVIEW && $node->isDefaultRevision()) {
      $form['actions']['workflow_' . $current_state]['#access'] = FALSE;
    }

    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $current_user = \Drupal::currentUser();
    $weight = RoleHierarchyHelper::getAccountRoleWeight($current_user);
    $coordinator_weight = Role::load('coordinator')->getWeight();
    // Coordinators can only add themselves as coordinator.
    if ($weight == $coordinator_weight) {
      if (!empty($form['field_coordinator']) && !empty($form['field_coordinator']['widget']['#options'])) {
        $form['field_coordinator']['widget']['#options'] = [
          '_none' => '- ' . t('None') . ' -',
          $current_user->id() => $current_user->getAccountName(),
        ];
      }
    }
    foreach (['field_coordinator', 'field_assessor', 'field_reviewers'] as $field) {
      if ($weight <= $coordinator_weight) {
        // Enable these fields only in certain assessment states
        // for roles >= coordinators.
        $enabled = $workflow_service->isFieldEnabledForAssessment($field, $node);
        $form[$field]['#access'] = $enabled;
        $form[$field]['widget']['#required'] = $enabled;
      }
      else {
        // Hide the field for lower roles.
        $form[$field]['#access'] = FALSE;
      }
    }
  }

  /**
   * Submit callback for the state change form.
   *
   * Redirects the user to the assessment edit page if he can access it.
   * Otherwise, this will redirect the user to /user.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function stateChangeSubmitRedirect(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    if ($workflow_service->hasAssessmentEditPermission(\Drupal::currentUser(), $node)) {
      $form_state->setRedirectUrl($node->toUrl('edit-form'));
    }
    else {
      $form_state->setRedirect('user.page');
    }
  }
}
