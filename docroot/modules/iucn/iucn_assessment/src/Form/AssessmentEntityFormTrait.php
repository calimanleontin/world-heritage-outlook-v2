<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

trait AssessmentEntityFormTrait {

  public static function hideUnnecessaryFields(array &$form) {
    // Hide unnecessary fields.
    unset($form['actions']['delete']);
    unset($form['advanced']);
    unset($form['revision']);
    unset($form['revision_log']);
    unset($form['author']);
    unset($form['meta']);
    if (!empty($form['field_state'])) {
      $form['field_state']['#access'] = FALSE;
    }
  }

  public static function addRedirectToAllActions(array &$form) {
    // Redirect to node edit on form submit.
    foreach ($form['actions'] as $key => &$action) {
      if (strpos($key, 'workflow_') !== FALSE || $key == 'submit') {
        $action['#submit'][] = [self::class, 'assessmentSubmitRedirect'];
      }
    }
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
    $tab = \Drupal::request()->query->get('tab');
    $options = [];
    if (!empty($tab)) {
      $options = ['query' => ['tab' => $tab]];
    }
    if ($workflow_service->checkAssessmentAccess($node, 'edit')->isAllowed()) {
      if (!$node->isDefaultRevision()) {
        $form_state->setRedirect('node.revision_edit', ['node' => $node->id(), 'node_revision' => $node->getRevisionId()], $options);
      }
      else {
        $form_state->setRedirectUrl($node->toUrl('edit-form', $options));
      }
    }
    elseif ($workflow_service->checkAssessmentAccess($node, 'change_state')->isAllowed()) {
      $form_state->setRedirect('iucn_assessment.node.state_change', ['node' => $node->id()]);
    }
    else {
      $form_state->setRedirect('who.user-dashboard');
    }
  }

  protected static function buildWrapperForField($fieldName) {
    return '#edit-' . str_replace('_', '-', $fieldName) . '-wrapper';
  }

}
