<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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

    $form['#title'] = t('Change state of @type @assessment', [
      '@type' => $node->type->entity->label(),
      '@assessment' => $node->getTitle(),
    ]);
  }

  public static function validateNode(&$form, NodeInterface $node) {
    $siteAssessmentFields = $node->getFieldDefinitions('node', 'site_assessment');
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

            if (in_array($fieldName, ['field_as_threats_current', 'field_as_threats_potential'])) {
              static::validateThreat($form, $paragraph);
            }

            if ($fieldName == 'field_as_benefits') {
              static::validateCategories($form, $paragraph->field_as_benefits_category, 'Benefits!');
              if (empty($node->field_as_benefits_summary->value)) {
                static::addStatusMessage($form, t('Field <b>Summary of benefits</b> is required in tab Benefits!'), 'error', $fieldName);
              }
            }

            if ($fieldName == 'field_as_values_bio') {
              static::validateAssessingValues($form, $node);
            }

            $paragraphFieldDefinitions = $paragraph->getFieldDefinitions();
            foreach ($paragraphFieldDefinitions as $paragraphFieldName => $paragraphFieldSettings) {
              if ($paragraphFieldSettings->isRequired() && empty($paragraph->{$paragraphFieldName}->getValue())) {
                $tab_has_errors = TRUE;
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
        self::addStatusMessage($form, t('<b>@label</b> field is required. Please fill it.', ['@label' => $fieldSettings->getLabel()]), 'error');
      }
    }

    if (!empty($form['error'])) {
      unset($form['field_coordinator']);
      unset($form['field_assessor']);
      unset($form['field_reviewers']);
      unset($form['warning']);
      unset($form['actions']);
    }
  }

  private static function validateThreat(&$form, $item) {
    if (empty($item->field_as_threats_out->value) &&
      empty($item->field_as_threats_in->value)) {
      static::addStatusMessage($form, t('At least one option must be selected for Inside site/Outside site in tab Threats!'), 'error');
    }

    if (!empty($item->field_as_threats_in->value) &&
      $item->field_as_threats_extent->isEmpty()) {
      static::addStatusMessage($form, t('Field <b>Threat extent</b> is required in tab Threat!'), 'error');
    }

    foreach (ParagraphAsSiteThreatForm::SUBCATEGORY_DEPENDENT_FIELDS as $key => $tids) {
      if ($item->$key->isEmpty() && !empty(array_intersect($tids, array_column($item->field_as_threats_categories->getValue(), 'target_id')))) {
        static::addStatusMessage($form, t('Field <b>@field</b> is required in tab Threats!', [
            '@field' => $item->getFieldDefinition($key)->getLabel(),
          ]
        ), 'error');
      }
    }

    $affectedValues = FALSE;
    foreach (ParagraphAsSiteThreatForm::AFFECTED_VALUES_FIELDS as $affectedField) {
      $affectedValues = $affectedValues || !$item->$affectedField->isEmpty();
    }

    if (!$affectedValues) {
      static::addStatusMessage($form, t('At least one affected value must be selected in tab Threats!'), 'error', 'field_affected_values');
    }

    static::validateCategories($form, $item->field_as_threats_categories, 'Threats!');
  }

  public static function validateBenefit(&$form, $node) {
    foreach ($node->field_as_benefits as $item) {
      static::validateCategories($form, $item->entity->field_as_benefits_category, 'Benefits!');
    }

    if ($node->field_as_benefits->getValue() && empty($node->field_as_benefits_summary->value)) {
      static::addStatusMessage($form, t('Field <b>Summary of benefits</b> is required in tab Benefits!'), 'error');
    }
  }

  public static function validateAssessingValues(&$form, $node) {
    if (empty($node->field_as_values_bio->getValue())) {
      return;
    }

    $required_fields = [
      'field_as_vass_bio_text',
      'field_as_vass_bio_state',
      'field_as_vass_bio_trend',
    ];

    $requiredLabels = [];
    foreach ($required_fields as $required_field) {
      if ($node->$required_field->isEmpty()) {
        $requiredLabels[] = $node->getFieldDefinition($required_field)->getLabel();
      }
    }

    if (!empty($requiredLabels)) {
      $labels = implode(', ', $requiredLabels);
      static::addStatusMessage(
        $form,
        \Drupal::translation()
          ->formatPlural(count($requiredLabels),
            t('Field <b>@field</b> should not be empty in Assessing values tab',
            [
              '@field' => $labels,
            ]
          ),
            t('Fields <b>@fields</b> should not be empty in Assessing values tab',
              [
                '@fields' => $labels,
              ]
            )
          ),
        'error',
        'field_as_values_bio'
      );
    }
  }

  private static function validateCategories(&$form, $items, $tab) {
    $mainCategory = FALSE;
    $skipSubcategories = FALSE;
    $subCategories = [];
    foreach ($items as $category) {
      $parent = array_column($category->entity->parent->getValue(), 'target_id');
      $parent = reset($parent);
      if (empty($parent)) {
        $mainCategory = $category->entity->id();
        $possibleSubCategories = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadChildren($mainCategory);
        if (empty($possibleSubCategories)) {
          $skipSubcategories = TRUE;
          break;
        }
        continue;
      }

      $subCategories[] = $category->entity->id();
      $mainCategory = $category->entity->parent->entity->id();
    }

    if (empty($mainCategory)) {
      static::addStatusMessage($form, t('Field <b>Category</b> is required in @tab tab!',
        [
          '@tab' => $tab,
        ]
      ),
        'error');
    }

    if (empty($subCategories) && !$skipSubcategories) {
      static::addStatusMessage($form, t('Select at least one <b>Subcategory</b> in @tab tab!',
        [
          '@tab' => $tab,
        ]
      ),
        'error');
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

  public static function addStatusMessage(&$form, $message, $type = 'warning', $key = NULL) {
    if (empty($form[$type])) {
      $form[$type] = [];
    }

    $message = [
      '#type' => 'markup',
      '#markup' => sprintf('<div role="contentinfo" aria-label="%s message" class="messages messages--%s">%s</div>',
        $type, $type, $message),
      '#weight' => -1000,
    ];

    if ($key) {
      $form[$type][$key] = $message;
      return;
    }

    $form[$type][] = $message;
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
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    /** @var \Drupal\node\NodeInterface $original */
    $original = clone($node);
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');
    $oldState = $newState = $node->field_state->value;
    $createNewRevision = TRUE;

    foreach (['field_coordinator', 'field_assessor', 'field_reviewers'] as $field) {
      $node->set($field, $form_state->getValue($field));
    }

    $triggeringAction = $form_state->getTriggeringElement();
    if (!empty($triggeringAction['#workflow']['to_sid'])) {
      $newState = $triggeringAction['#workflow']['to_sid'];
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
      $createNewRevision = FALSE;
    }

    $default = $node->isDefaultRevision();
    $settingsWithDifferences = $node->field_settings->value;
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
          $workflowService->createRevision($defaultUnderReviewRevision, $newState, NULL, "{$oldState} ({$defaultUnderReviewRevision->getRevisionId()}) => {$newState}", TRUE);
        }
        $node->setRevisionLogMessage("{$oldState} => {$newState}");
        $createNewRevision = FALSE;
        break;

      case AssessmentWorkflow::STATUS_FINISHED_REVIEWING . '>' . AssessmentWorkflow::STATUS_UNDER_COMPARISON:
        $node->set('field_settings', $settingsWithDifferences);
        break;

      case AssessmentWorkflow::STATUS_PUBLISHED . '>' . AssessmentWorkflow::STATUS_DRAFT:
        $default = FALSE;
        break;
    }

    if ($createNewRevision === TRUE) {
      $entity = $workflowService->createRevision($node, $newState, NULL, "{$oldState} ({$node->getRevisionId()}) => {$newState}", $default);
    }
    else {
      $workflowService->forceAssessmentState($node, $newState);
      $entity = $node;
    }

    $nodeForm->setEntity($entity);
    $form_state->setFormObject($nodeForm);
    \Drupal::messenger()->addMessage(t('The assessment "%assessment" was successfully updated.', ['%assessment' => $entity->getTitle()]));
  }
}
