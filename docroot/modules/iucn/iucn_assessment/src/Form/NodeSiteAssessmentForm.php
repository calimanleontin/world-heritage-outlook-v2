<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\workflow\Entity\WorkflowState;

class NodeSiteAssessmentForm {

  public static function alter(&$form, FormStateInterface $form_state, $form_id) {
    $request = \Drupal::request();
    $tab = $request->get('tab') ?: 'values';

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;

    foreach (['status', 'revision_log', 'revision_information', 'revision'] as $item) {
      $form[$item]['#access'] = FALSE;
    }

    // Hide all revision related settings and check if a new revision should
    // be created in hook_node_presave().
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;

    if (!empty($node->id()) && !empty($state)) {
      $state_entity = WorkflowState::load($state);
      $form['current_state'] = [
        '#weight' => -100,
        '#type' => 'markup',
        '#markup' => t('Current state: <b>@state</b>', ['@state' =>  $state_entity->label()]),
      ];
      if (in_array($state, [AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, AssessmentWorkflow::STATUS_UNDER_REVIEW])) {
        $settings = json_decode($node->field_settings->value, TRUE);
        $fieldgroup_key = 'group_as_' . str_replace('-', '_', $tab);
        $comment_title = !empty($form['#fieldgroups'][$fieldgroup_key]->label)
          ? t('Comment about "@group"', ['@group' => $form['#fieldgroups'][$fieldgroup_key]->label])
          : t('Comment about current tab');
        $form["comment_$tab"] = [
          '#type' => 'textarea',
          '#title' => $comment_title,
          '#weight' => !empty($form['#fieldgroups'][$fieldgroup_key]->weight) ? $form['#fieldgroups'][$fieldgroup_key]->weight - 1 : 0,
          '#default_value' => !empty($settings['comments'][$tab]) ? $settings['comments'][$tab] : '',
        ];
      }
    }

    array_unshift($form['actions']['submit']['#submit'], [self::class, 'setAssessmentSettings']);
    $form['actions']['submit']['#submit'][] = [self::class, 'assessmentSubmitRedirect'];
  }

  /**
   * Store comments on node.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function setAssessmentSettings(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $values = $form_state->getValues();

    $settings = json_decode($node->field_settings->value, TRUE);
    foreach ($values as $key => $value) {
      if (preg_match('/^comment\_(.+)$/', $key, $matches)) {
        $commented_tab = $matches[1];
        $settings['comments'][$commented_tab] = $value;
      }
    }
    $node->field_settings->setValue(json_encode($settings));
    $nodeForm->setEntity($node);
    $form_state->setFormObject($nodeForm);
  }

  /**
   * Submit callback for the state change form.
   *
   * Redirects the user to the assessment edit page if he can access it.
   * Otherwise, this will redirect the user to /user.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public static function assessmentSubmitRedirect(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
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

}
