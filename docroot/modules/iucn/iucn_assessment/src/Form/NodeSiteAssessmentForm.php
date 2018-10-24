<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\workflow\Entity\WorkflowState;

class NodeSiteAssessmentForm {

  public static function alter(&$form, FormStateInterface $form_state, $form_id) {
    foreach (['status', 'revision_log', 'revision_information', 'revision'] as $item) {
      $form[$item]['#access'] = FALSE;
    }

    $tab = \Drupal::request()->query->get('tab');
    // On the values tab, only coordinators and above can edit the values.
    if (empty($tab)) {
      $account = \Drupal::currentUser();
      $account_role_weight = RoleHierarchyHelper::getAccountRoleWeight($account);
      $coordinator_weight = Role::load('coordinator')->getWeight();

      if ($account_role_weight > $coordinator_weight) {
        self::hideParagraphsActions($form);
      }
    }

    // Hide all revision related settings and check if a new revision should
    // be created in hook_node_presave().
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;

    // Add the current state on the node edit page.
    if ($form_id == 'node_site_assessment_edit_form') {
      $node = $form_state->getFormObject()->getEntity();
      $form['current_state'] = self::getCurrentStateMarkup($node);
    }
    $form['actions']['submit']['#submit'][] = ['Drupal\iucn_assessment\Form\NodeSiteAssessmentForm', 'assessmentSubmitRedirect'];
  }

  /**
   * Get the markup for the current state label.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $weight
   *   The weight of the state label.
   *
   * @return array
   *   The renderable array.
   */
  public static function getCurrentStateMarkup(NodeInterface $node, $weight = -1000) {
    $current_state = $node->field_state->value;
    if (!empty($current_state)) {
      $state_entity = WorkflowState::load($current_state);
    }
    else {
      $state_entity = NULL;
    }
    $state_label = !empty($state_entity) ? $state_entity->label() : 'Creation';
    return [
      '#weight' => $weight,
      '#type' => 'markup',
      '#markup' => t('Current state: <b>@state</b>', ['@state' => $state_label]),
    ];
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
  public static function assessmentSubmitRedirect(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    if ($workflow_service->hasAssessmentEditPermission(\Drupal::currentUser(), $node)) {
      if ($workflow_service->isAssessmentEditable($node)) {
        $form_state->setRedirectUrl($node->toUrl('edit-form'));
      }
      else {
        $form_state->setRedirect('iucn_assessment.node.state_change', ['node' => $node->id()]);
      }
    }
    else {
      $form_state->setRedirect('user.page');
    }
  }

  /**
   * Hide all paragraphs actions on a form.
   *
   * @param array $form
   *   The form.
   */
  public static function hideParagraphsActions(array &$form) {
    $read_only_paragraph_fields = ['field_as_values_bio', 'field_as_values_wh'];
    foreach ($read_only_paragraph_fields as $field) {
      $form[$field]['widget']['add_more']['#access'] = FALSE;
      $paragraphs = &$form[$field]['widget'];
      foreach ($paragraphs as $key => &$paragraph) {
        if (!is_int($key)) {
          continue;
        }
        $paragraph['top']['actions']['#access'] = FALSE;
      }
      $paragraphs['add_mode']['#access'] = FALSE;
    }
  }

}
