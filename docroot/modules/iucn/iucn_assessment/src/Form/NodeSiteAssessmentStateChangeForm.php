<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;

class NodeSiteAssessmentStateChangeForm {

  public static function alter(&$form, FormStateInterface $form_state) {
    // Hide state change scheduling.
    if (!empty($form['field_state']['widget'][0]['workflow_scheduling'])) {
      $form['field_state']['widget'][0]['workflow_scheduling']['#access'] = FALSE;
    }

    $form['advanced']['#access'] = FALSE;
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;
    $form['revision']['#access'] = FALSE;
    $form['field_state']['#access'] = FALSE;

    $form['#validate'][] = '_iucn_assessment_edit_form_validate';
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $weight = RoleHierarchyHelper::getAccountRoleWeight(\Drupal::currentUser());
    $coordinator_weight = Role::load('coordinator')->getWeight();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    foreach (['field_coordinator', 'field_assessor', 'field_reviewers'] as $field) {
      if ($weight <= $coordinator_weight) {
        // Enable these fields only in certain assessment states
        // for roles >= coordinators.
        $enabled = $workflow_service->isFieldEnabledForAssessment($field, $node);
        $form[$field]['#access'] = $enabled;
      }
      else {
        // Hide the field for lower roles.
        $form[$field]['#access'] = FALSE;
      }
    }
  }
}