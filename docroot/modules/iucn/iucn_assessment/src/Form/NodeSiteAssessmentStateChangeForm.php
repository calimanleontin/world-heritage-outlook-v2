<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\role_hierarchy\RoleHierarchyHelper;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
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

    self::validateNode($form, $node);
    self::addStateChangeWarning($form, $node, $currentUser);
    self::hideUnnecessaryFields($form);

    // We want to replace the core submitForm method so the node won't get saved
    // twice.
    $form['#submit'] = [[self::class, 'submitForm']];
    foreach ($form['actions'] as $key => &$action) {
      if (strpos($key, 'workflow_') !== FALSE || $key == 'submit') {
        $action['#submit'] = [[self::class, 'submitForm']];
      }
    }
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
  }

  public static function validateNode($form, NodeInterface $node) {
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

  /**
   * Handles /node/xxx/state_change form submit. This method replaces the core
   * method ContentEntityForm::submitForm.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function submitForm(&$form, FormStateInterface $form_state) {
    $triggeringAction = $form_state->getTriggeringElement();
    if (empty($triggeringAction['#workflow']['to_sid'])) {
      return;
    }

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    /** @var \Drupal\node\NodeInterface $original */
    $original = clone($node);
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');
    $oldState = $node->field_state->value;
    $newState = $triggeringAction['#workflow']['to_sid'];

    foreach (['field_coordinator', 'field_assessor', 'field_reviewers'] as $field) {
      $node->set($field, $form_state->getValue($field));
    }

    if ($newState == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
      // Handle reviewers revisions.
      $originalReviewers = ($oldState == AssessmentWorkflow::STATUS_UNDER_REVIEW)
        ? $workflowService->getReviewersArray($original)
        : [];
      $newReviewers = $workflowService->getReviewersArray($node);

      $addedReviewers = array_diff($newReviewers, $originalReviewers);
      $removedReviewers = array_diff($originalReviewers, $newReviewers);

      if (!empty($addedReviewers)) {
        // Create a revision for each newly added reviewer.
        foreach ($addedReviewers as $reviewerId) {
          if (empty($workflowService->getReviewerRevision($node, $reviewerId))) {
            $message = "Revision created for reviewer {$reviewerId}";
            $workflowService->createRevision($node, $newState, $reviewerId, $message);
          }
        }
      }

      if (!empty($removedReviewers)) {
        // Delete revisions of reviewers no longer assigned on this assessment.
        foreach ($removedReviewers as $reviewerId) {
          $workflowService->deleteReviewerRevisions($node, $reviewerId);
        }
      }

      if (empty($workflowService->getUnfinishedReviewerRevisions($node))) {
        // When all reviewers finished their work, we send the assessment back
        // to the coordinator.
        $newState = AssessmentWorkflow::STATUS_FINISHED_REVIEWING;
      }
    }

    if ($oldState == $newState) {
      // The state hasn't changed. No further actions needed.
      return;
    }

    $default = $node->isDefaultRevision();
    $workflowService->clearKeyFromFieldSettings($node, 'diff');

    switch ($oldState . '>' . $newState) {
      case AssessmentWorkflow::STATUS_UNDER_ASSESSMENT . '>' . AssessmentWorkflow::STATUS_READY_FOR_REVIEW:
        $underEvaluationRevision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
        $workflowService->appendDiffToFieldSettings($node, $underEvaluationRevision->getRevisionId(), $original->getRevisionId());
        break;

      case AssessmentWorkflow::STATUS_UNDER_REVIEW . '>' . AssessmentWorkflow::STATUS_FINISHED_REVIEWING:
        $defaultUnderReviewRevision = Node::load($node->id());
        $readyForReviewRevision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);

        // Save the differences on the revision "under review" revision.
        $workflowService->appendCommentsToFieldSettings($defaultUnderReviewRevision, $node);
        $workflowService->appendDiffToFieldSettings($defaultUnderReviewRevision, $readyForReviewRevision->getRevisionId(), $node->getRevisionId());
        $defaultUnderReviewRevision->setNewRevision(FALSE);
        $defaultUnderReviewRevision->save();

        if ($workflowService->isAssessmentReviewed($defaultUnderReviewRevision, $node->getRevisionId())) {
          // If all other reviewers finished their work, send the assessment
          // back to the coordinator.
          $workflowService->createRevision($defaultUnderReviewRevision, $newState, NULL, "{$oldState} => {$newState}", TRUE);
        }
        break;

      case AssessmentWorkflow::STATUS_PUBLISHED . '>' . AssessmentWorkflow::STATUS_DRAFT:
        $default = FALSE;
        break;
    }

    $newRevision = $workflowService->createRevision($node, $newState, NULL, "{$oldState} => {$newState}", $default);
    $nodeForm->setEntity($newRevision);
    $form_state->setFormObject($nodeForm);
    \Drupal::messenger()->addMessage(t('The assessment "%assessment" was successfully updated.', ['%assessment' => $newRevision->getTitle()]));
  }
}
