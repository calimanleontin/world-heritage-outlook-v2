<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\paragraphs\Entity\Paragraph;

class NodeSiteAssessmentStateChangeForm {

  use AssessmentEntityFormTrait;

  public static function alter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;
    $currentUser = \Drupal::currentUser();

    $siteAssessmentFields = $node->getFieldDefinitions('node', 'site_assessment');
    $validation_error = FALSE;
    foreach ($siteAssessmentFields as $fieldName => $fieldSettings) {
      $tab_has_errors = FALSE;
      if (!$fieldSettings->isRequired() && ($fieldSettings->getType() != 'entity_reference_revisions')) {
        continue;
      }
      if (!empty($node->{$fieldName}->getValue()) || !$fieldSettings->isRequired()) {
        if ($fieldSettings->getType() == 'entity_reference_revisions') {
          foreach ($node->{$fieldName} as &$value) {
            $target = $value->getValue();
            $paragraph = Paragraph::load($target['target_id']);
            $paragraphFieldDefinitions = $paragraph->getFieldDefinitions();
            foreach ($paragraphFieldDefinitions as $paragraphFieldName => $paragraphFieldSettings) {
              if ($paragraphFieldSettings->isRequired() && empty($paragraph->{$paragraphFieldName}->getValue())) {
                $tab_has_errors = $validation_error = TRUE;
                self::addStatusMessage($form, t('<b>@field</b> field is required for all rows in "@label" table. Please fill it.', [
                  '@field' => $paragraphFieldSettings->getLabel(),
                  '@label' => $fieldSettings->getLabel(),
                ]), 'error');
              }
            }
            // Show errors only in 1 paragraph row.
            if (!empty($tab_has_errors)) {
              break;
            }
          }
        }
      }
      else {
        $validation_error = TRUE;
        self::addStatusMessage($form, t('<b>@label</b> field is required. Please fill it.', ['@label' => $fieldSettings->getLabel()]), 'error');
      }
    }

    self::addStateChangeWarning($form, $node, $currentUser);

    self::hideUnnecessaryFields($form);
    self::addRedirectToAllActions($form);

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

      self::addStatusMessage($form, t("You have not added any new references. Are you sure you haven't forgotten any references?"));
    }


    if (!empty($validation_error)) {
      unset($form['field_coordinator']);
      unset($form['field_assessor']);
      unset($form['field_reviewers']);
      unset($form['warning']);
      unset($form['actions']);
    }
  }

  /**
   * Checks if any references were added by the user to the current revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public static function assessmentHasNewReferences(NodeInterface $node) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');
    $originalRevision = $workflowService->getPreviousWorkflowRevision($node);
    $originalValue = !empty($originalRevision->field_as_references_p)
      ? array_column($originalRevision->field_as_references_p->getValue(), 'target_id')
      : [];
    $newValue = !empty($node->field_as_references_p)
      ? array_column($node->field_as_references_p->getValue(), 'target_id')
      : [];
    return !empty(array_diff($newValue, $originalValue));
  }

  public static function addStatusMessage(&$form, $message, $type = 'warning') {
    if (empty($form[$type])) {
      $form[$type] = [];
    }
    $form[$type][] = [
      '#type' => 'markup',
      '#markup' => sprintf('<div role="contentinfo" aria-label="%s message" class="messages messages--%s">%s</div>',
        $type, $type, $message),
      '#weight' => -1000,
    ];
  }

  public static function addStateChangeWarning(&$form, NodeInterface $node, AccountInterface $current_user) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $state = $node->field_state->value;
    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT
      && $node->field_assessor->target_id == $current_user->id()) {
      self::addStatusMessage($form, t('You will NO longer be able to edit the assessment after you finish it.'));
    }
    elseif ($state == AssessmentWorkflow::STATUS_UNDER_REVIEW
      && in_array($current_user->id(), $assessment_workflow->getReviewersArray($node))) {
      self::addStatusMessage($form, t('You will NO longer be able to edit the assessment after you finish reviewing it.'));
    }
    elseif ($node->field_coordinator->target_id == $current_user->id()) {
      if ($state == AssessmentWorkflow::STATUS_UNDER_EVALUATION) {
        self::addStatusMessage($form, t('You will NO longer be able to edit the assessment until the assessor finishes his work.'));
      }
      elseif ($state == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        self::addStatusMessage($form, t('You will NO longer be able to edit the assessment until all reviewers finish their work.'));
      }
    }

  }

}
