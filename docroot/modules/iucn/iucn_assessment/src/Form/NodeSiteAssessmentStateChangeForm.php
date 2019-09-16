<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class NodeSiteAssessmentStateChangeForm {

  use AssessmentEntityFormTrait;

  public static function alter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;

    if ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
      // Redirect the user to state change form of the draft assessment.
      $draft_revision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_DRAFT);
      if (!empty($draft_revision)) {
        $url = Url::fromRoute('iucn_assessment.node_revision.state_change', ['node' => $node->id(), 'node_revision' => $draft_revision->getRevisionId()]);
        $response = new RedirectResponse($url->setAbsolute()->toString());
        $response->send();
      }
    }

    $currentUser = \Drupal::currentUser();
    $coordinator = !empty($node->field_coordinator->target_id)
      ? $node->field_coordinator->target_id
      : NULL;
    $currentUserIsCoordinator = $currentUser->id() === $coordinator || $currentUser->hasPermission('edit assessment in any state');

    if ($workflowService->isNewAssessment($node) === FALSE
      && $state != AssessmentWorkflow::STATUS_PUBLISHED) {
      self::validateNode($form, $node);
      if (empty($form['error'])) {
        self::addStateChangeWarning($form, $node, $currentUser);
      }
    }
    self::hideUnnecessaryFields($form);

    $form['actions']['workflow_force_finish_review'] = [
      '#type' => 'submit',
      '#value' => t('Force finish reviewing'),
      '#access' => $state == AssessmentWorkflow::STATUS_UNDER_REVIEW
        && $currentUser->hasPermission('force finish reviewing')
        && $currentUserIsCoordinator,
      '#weight' => 100,
      '#name' => 'force_finish_review',
      '#attributes' => [
        'class' => ['button--danger'],
        'onclick' => 'if(!confirm("Are you sure you want to force the finalization of the reviewing phase? Reviewers will no longer be able to edit this assessment.")){return false;}',
      ],
    ];

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

    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT && $currentUser->hasPermission('force finish assessment')) {
      $form['actions']['workflow_' . $state]['#access'] = TRUE;
      $form['actions']['workflow_assessment_ready_for_review']['#value'] = t('Force finish assessment');
      $form['actions']['workflow_assessment_ready_for_review']['#attributes'] = [
        'class' => ['button--danger'],
        'onclick' => 'if(!confirm("Are you sure you want to force the finalization of the assessment phase? The assessor will no longer be able to edit this assessment.")){return false;}',
      ];
    }

    if (in_array('coordinator', $currentUser->getRoles())
      && $currentUser->hasPermission('assign any coordinator to assessment') === FALSE
      && empty($form['field_coordinator']['widget']['#default_value'])) {
      $form['field_coordinator']['widget']['#options'] = [
        '_none' => t('- Select -'),
        $currentUser->id() => $currentUser->getAccountName(),
      ];
      $form['field_coordinator']['widget']['#default_value'] = $currentUser->id();
    }

    $form['field_coordinator']['widget']['#required'] = in_array($state, [NULL, AssessmentWorkflow::STATUS_CREATION, AssessmentWorkflow::STATUS_NEW]);
    $form['field_assessor']['widget']['#required'] = in_array($state, [AssessmentWorkflow::STATUS_UNDER_EVALUATION, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT]);
    $form['field_reviewers']['widget']['#required'] = in_array($state, [AssessmentWorkflow::STATUS_READY_FOR_REVIEW, AssessmentWorkflow::STATUS_UNDER_REVIEW]);
    $form['field_references_reviewer']['widget']['#required'] = in_array($state, [AssessmentWorkflow::STATUS_UNDER_COMPARISON]);
    if ($currentUser->hasPermission('assign users to assessments')) {
      $form['field_coordinator']['#disabled'] = !$form['field_coordinator']['widget']['#required'];
      $form['field_assessor']['#disabled'] = !$form['field_assessor']['widget']['#required'] || !$currentUserIsCoordinator;
      $form['field_reviewers']['#disabled'] = !$form['field_reviewers']['widget']['#required'] || !$currentUserIsCoordinator;
      $form['field_references_reviewer']['#disabled'] = !$form['field_references_reviewer']['widget']['#required'] || !$currentUserIsCoordinator;
    }
    else {
      $form['field_coordinator']['#disabled'] = TRUE;
      $form['field_assessor']['#disabled'] = TRUE;
      $form['field_reviewers']['#disabled'] = TRUE;
      $form['field_references_reviewer']['#disabled'] = TRUE;
    }

    if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT) {
      if ($node->field_assessor->target_id == $currentUser->id() && !self::assessmentHasNewReferences($node)) {
        self::addStatusMessage($form, t("You have not added any new references. Are you sure you haven't forgotten any references?"));
      }
      if ($currentUserIsCoordinator) {
        self::addStatusMessage($form, t("If you change the assessor, the current revision will be deleted and a new one will be created from scratch for the new assessor. "));
        self::addStatusMessage($form, t("If you want to preserve the current assessor's changes, but move further with the assessment workflow, press \"Force finish assessment\" button."));
      }
    }

    $form['#title'] = t('Submit @assessment', ['@assessment' => $node->getTitle()]);
    static::changeWorkflowButtons($form, $currentUser);

    $titlePlaceholder = 'Change state of @type @assessment';

    $titlePlaceholders = [
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => 'Submit assessment of @assessment',
      AssessmentWorkflow::STATUS_UNDER_REVIEW => 'Submit review of @assessment @type',
      AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => 'Submit review of @assessment @type',
    ];

    if (!empty($titlePlaceholders[$state])) {
      $titlePlaceholder = $titlePlaceholders[$state];
    }

    $form['#title'] = t($titlePlaceholder, [
      '@type' => $node->type->entity->label(),
      '@assessment' => $node->getTitle(),
    ]);
  }

  public static function validateNode(&$form, NodeInterface $node) {
    /** @var \Drupal\Core\Field\FieldConfigInterface[] $siteAssessmentFields */
    $siteAssessmentFields = $node->getFieldDefinitions('node', 'site_assessment');
    $errors = [];

    foreach ($siteAssessmentFields as $fieldName => $fieldSettings) {
      if (!static::isAssessmentFieldVisible($fieldName)) {
        continue;
      }
      // First we do custom validation for some fields.
      switch ($fieldName) {
        case 'field_as_vass_bio_text':
          $fieldSettings->setLabel(t('Justification of assessment'));
        case 'field_as_vass_bio_state':
        case 'field_as_vass_bio_trend':
          $fieldSettings->setLabel(t('Summary of the values - ' . $fieldSettings->getLabel()));
          if (!empty($node->field_as_values_bio->getValue())) {
            // These 3 fields are required only if field_as_values_bio is not empty.
            $fieldSettings->setRequired(TRUE);
          }
          break;

        case 'field_as_benefits_summary':
          // This field is required only if field_as_benefits is not empty.
          if (!empty($node->field_as_benefits->getValue())) {
            $fieldSettings->setRequired(TRUE);
          }
          break;
      }

      if ($fieldSettings->isRequired() == FALSE && ($fieldSettings->getType() != 'entity_reference_revisions')) {
        continue;
      }

      if ($fieldSettings->isRequired() && empty($node->{$fieldName}->getValue())) {
        $errors[$fieldName][$fieldName] = $fieldSettings->getLabel();
        continue;
      }

      if ($fieldSettings->getType() == 'entity_reference_revisions') {
        foreach ($node->{$fieldName} as &$value) {
          // We need to validate each child paragraph.
          $target = $value->getValue();

          $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->loadRevision($target['target_revision_id']);
          if ($paragraph->bundle() == 'as_site_threat') {
            static::validateThreat($form, $paragraph);
          }

          if ($paragraph->bundle() == 'as_site_benefit') {
            $categoryError =  static::validateTaxonomyReferenceFieldWithTwoLevels($paragraph->field_as_benefits_category);
            if (!empty($categoryError)) {
              $errors[$fieldName][$categoryError] = ($categoryError == 'main') ? t('Benefit type') : t('Specific benefits');
            }
          }

          /** @var \Drupal\Core\Field\FieldConfigInterface[] $paragraphFieldDefinitions */
          $paragraphFieldDefinitions = $paragraph->getFieldDefinitions();
          foreach ($paragraphFieldDefinitions as $paragraphFieldName => $paragraphFieldSettings) {
            if ($paragraphFieldSettings->isRequired() && empty($paragraph->{$paragraphFieldName}->getValue())) {
              if (in_array($paragraphFieldName, [
                'field_as_values_curr_text',
                'field_as_values_curr_state',
                'field_as_values_curr_trend',
              ])) {
                $siteAssessmentFields['assessing_values'] = clone $fieldSettings;
                $siteAssessmentFields['assessing_values']->setLabel('Assessing values');
                $errors['assessing_values'][$paragraphFieldName] = $paragraphFieldSettings->getLabel();
              }
              else {
                $errors[$fieldName][$paragraphFieldName] = $paragraphFieldSettings->getLabel();
              }
            }
          }
        }
      }
    }

    foreach($errors as $parentField => $errorData) {
      if (key($errorData) == $parentField) {
        self::addStatusMessage($form, t('<b>@name</b> field is required.', ['@name' => reset($errorData)]), 'error');
        continue;
      }

      $singularMessage = '<b>@field</b> field is required for all rows in <b>@table</b> table.';
      $pluralMessage = '<b>@field</b> fields are required for all rows in <b>@table</b> table.';
      self::addStatusMessage($form, new PluralTranslatableMarkup(count($errorData), $singularMessage, $pluralMessage, [
        '@field' => implode(', ', $errorData),
        '@table' => $siteAssessmentFields[$parentField]->getLabel(),
      ]), 'error');
    }

    if (!empty($form['error'])) {
      unset($form['field_coordinator']);
      unset($form['field_assessor']);
      unset($form['field_reviewers']);
      unset($form['field_references_reviewer']);
      unset($form['warning']);
      $form['actions']['#access'] = FALSE;
    }
  }

  private static function isAssessmentFieldVisible($field) {
    $form_modes = [
      'default',
      'assessing_values',
      'conservation_outlook',
    ];

    foreach ($form_modes as $form_mode) {
      $hidden_fields = \Drupal::configFactory()->getEditable("core.entity_form_display.node.site_assessment.$form_mode")->get('hidden');
      if (!in_array($field, array_keys($hidden_fields))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  private static function validateThreat(&$form, ParagraphInterface $paragraph) {
    $threatTitle = $paragraph->get('field_as_threats_threat')->value;
    $categoryError =  static::validateTaxonomyReferenceFieldWithTwoLevels($paragraph->field_as_threats_categories);
    if ($categoryError !== FALSE) {
      static::addStatusMessage($form, t('<b>@field</b> field is required for <i>"@threat"</i> threat.', [
        '@field' => ($categoryError == 'main') ? t('Category') : t('Subcategories'),
        '@threat' => $threatTitle,
      ]), 'error');
    }

    if (empty($paragraph->get('field_as_threats_out')->value) &&
      empty($paragraph->get('field_as_threats_in')->value)) {
      static::addStatusMessage($form, t('At least one option must be selected for <b>Inside site/Outside site</b> for <i>"@threat"</i> threat.', [
        '@threat' => $threatTitle,
      ]), 'error');
    }

    if (!empty($paragraph->get('field_as_threats_in')->value) &&
      $paragraph->get('field_as_threats_extent')->isEmpty()) {
      static::addStatusMessage($form, t('<b>@field</b> field is required for <i>"@threat"</i> threat.', [
        '@field' => t('Threat extent'),
        '@threat' => $threatTitle,
      ]), 'error');
    }

    foreach (ParagraphAsSiteThreatForm::SUBCATEGORY_DEPENDENT_FIELDS as $key => $tids) {
      if ($paragraph->$key->isEmpty()
        && in_array($key, ParagraphAsSiteThreatForm::REQUIRED_DEPENDENT_FIELDS)
        && !empty(array_intersect($tids, array_column($paragraph->get('field_as_threats_categories')->getValue(), 'target_id')))) {
        static::addStatusMessage($form, t('<b>@field</b> field is required for <i>"@threat"</i> threat.', [
          '@field' => $paragraph->getFieldDefinition($key)->getLabel(),
          '@threat' => $threatTitle,
        ]), 'error');
      }
    }

    $affectedValues = FALSE;
    foreach (ParagraphAsSiteThreatForm::AFFECTED_VALUES_FIELDS as $affectedField) {
      $affectedValues = $affectedValues || !$paragraph->get($affectedField)->isEmpty();
    }

    if (!$affectedValues) {
      static::addStatusMessage($form, t('<b>@field</b> field is required for <i>"@threat"</i> threat.', [
        '@field' => t('Affected values'),
        '@threat' => $threatTitle,
      ]), 'error', 'field_affected_values');
    }
  }

  /**
   * There are some entity reference fields which are required to have both
   * level 1 terms and at least one of their child.
   *
   * @param $items
   *
   * @return bool|string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private static function validateTaxonomyReferenceFieldWithTwoLevels(EntityReferenceFieldItemList $items = NULL) {
    $mainCategory = FALSE;
    $skipSubcategories = FALSE;
    $subCategories = [];

    if (empty($items)) {
      return 'main';
    }

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
      return 'main';
    }

    if (empty($subCategories) && !$skipSubcategories) {
      return 'sub';
    }
    return FALSE;
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
      self::addStatusMessage($form, t('You are about to submit your assessment. You will no longer be able to edit the assessment. To proceed and submit to IUCN, please press submit below.'));
    }
    elseif (($state == AssessmentWorkflow::STATUS_UNDER_REVIEW && in_array($current_user->id(), $assessment_workflow->getReviewersArray($node)))
      || ($state == AssessmentWorkflow::STATUS_REVIEWING_REFERENCES && $current_user->id() == $node->field_references_reviewer->target_id)) {
      self::addStatusMessage($form, t('You are about to submit your review. You will no longer be able to edit the assessment. To proceed and submit your review to IUCN, please press submit review below.'));
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

    foreach (['field_coordinator', 'field_assessor', 'field_reviewers', 'field_references_reviewer'] as $field) {
      if (empty($form_state->getValue($field))) {
        continue;
      }
      $node->set($field, $form_state->getValue($field));
    }

    $triggeringAction = $form_state->getTriggeringElement();
    if (!empty($triggeringAction['#workflow']['to_sid'])) {
      $newState = $triggeringAction['#workflow']['to_sid'];
    }

    if ($newState == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
      $removedReviewers = $addedReviewers = [];

      if (!empty($form_state->getValue('force_finish_review'))) {
        $underReviewRevisions = $workflowService->getAllReviewersRevisions($node);
        /** @var NodeInterface $revision */
        foreach ($underReviewRevisions as $revision) {
          if ($revision->get('field_state')->value != AssessmentWorkflow::STATUS_UNDER_REVIEW) {
            continue;
          }

          $removedReviewers[] = $revision->getRevisionUserId();
        }
      }
      else {
        // Handle reviewers revisions.
        $originalReviewers = ($oldState == AssessmentWorkflow::STATUS_UNDER_REVIEW)
          ? $workflowService->getReviewersArray($original)
          : [];
        $newReviewers = $workflowService->getReviewersArray($node);

        $addedReviewers = array_diff($newReviewers, $originalReviewers);
        $removedReviewers = array_diff($originalReviewers, $newReviewers);
      }

      if (!empty($addedReviewers)) {
        // Create a revision for each newly added reviewer.
        foreach ($addedReviewers as $reviewerId) {
          $reviewerRevision = $workflowService->getReviewerRevision($node, $reviewerId);
          if (empty($reviewerRevision)) {
            $message = "Revision created for reviewer {$reviewerId}";
            $workflowService->createRevision($node, $newState, $reviewerId, $message);
          } else {
            $reviewerRevision->set('field_state', AssessmentWorkflow::STATUS_UNDER_REVIEW);
            $reviewerRevision->save();
          }
        }
      }

      if (!empty($removedReviewers)) {
        // Delete revisions of reviewers no longer assigned on this assessment.
        foreach ($removedReviewers as $reviewerId) {
          $reviewerRevision = $workflowService->getReviewerRevision($node, $reviewerId);
          $readyForReviewRevision = $workflowService->getRevisionByState($reviewerRevision, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);

          $workflowService->markRevisionAsFinished($node, $reviewerRevision, $readyForReviewRevision);
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
    if ($oldState != AssessmentWorkflow::STATUS_UNDER_REVIEW) {
      $workflowService->clearKeyFromFieldSettings($node, 'diff');
    }

    $underAssessmentRevisionOld = NULL;
    switch ($oldState . '>' . $newState) {
      case AssessmentWorkflow::STATUS_UNDER_ASSESSMENT . '>' . AssessmentWorkflow::STATUS_UNDER_ASSESSMENT:
        $underAssessmentRevisionOld = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);
        if ($underAssessmentRevisionOld->get('field_assessor')->target_id == $node->get('field_assessor')->target_id) {
          $underAssessmentRevisionOld = NULL;
          break;
        }

        $workflowService->clearKeyFromFieldSettings($node, 'comments');
        $nodeClone = $node;
        $node = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
        $node->set('field_assessor', $nodeClone->get('field_assessor')->target_id);
        $oldState = AssessmentWorkflow::STATUS_UNDER_EVALUATION;
        $createNewRevision = true;
        break;

      case AssessmentWorkflow::STATUS_UNDER_ASSESSMENT . '>' . AssessmentWorkflow::STATUS_READY_FOR_REVIEW:
        $underEvaluationRevision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
        $workflowService->appendDiffToFieldSettings($node, $underEvaluationRevision->getRevisionId(), $original->getRevisionId());
        break;

      case AssessmentWorkflow::STATUS_READY_FOR_REVIEW . '>' . AssessmentWorkflow::STATUS_UNDER_REVIEW:
      case AssessmentWorkflow::STATUS_UNDER_COMPARISON . '>' . AssessmentWorkflow::STATUS_REVIEWING_REFERENCES:
        $workflowService->clearKeyFromFieldSettings($node, 'comments');
        break;

      case AssessmentWorkflow::STATUS_UNDER_REVIEW . '>' . AssessmentWorkflow::STATUS_FINISHED_REVIEWING:
        if (!empty($removedReviewers)) {
          break;
        }

        $defaultUnderReviewRevision = Node::load($node->id());
        $readyForReviewRevision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_READY_FOR_REVIEW);
        $workflowService->markRevisionAsFinished($defaultUnderReviewRevision, $node, $readyForReviewRevision);

        $createNewRevision = FALSE;
        break;

      case AssessmentWorkflow::STATUS_FINISHED_REVIEWING . '>' . AssessmentWorkflow::STATUS_UNDER_COMPARISON:
        $node->set('field_settings', $settingsWithDifferences);
        break;

      case AssessmentWorkflow::STATUS_REVIEWING_REFERENCES . '>' . AssessmentWorkflow::STATUS_FINAL_CHANGES:
        $underComparisonRevision = $workflowService->getRevisionByState($node, AssessmentWorkflow::STATUS_UNDER_COMPARISON);
        $workflowService->appendDiffToFieldSettings($node, $underComparisonRevision->getRevisionId(), $original->getRevisionId());
        break;

      case AssessmentWorkflow::STATUS_PUBLISHED . '>' . AssessmentWorkflow::STATUS_DRAFT:
        $default = FALSE;
        break;

      case AssessmentWorkflow::STATUS_DRAFT . '>' . AssessmentWorkflow::STATUS_PUBLISHED:
        $workflowService->forceAssessmentState($node, $newState);
        $default = TRUE;
        $createNewRevision = TRUE;
        break;
    }

    $entity = $node;
    if (empty($removedReviewers)) {
      if ($createNewRevision === TRUE) {
        $entity = $workflowService->createRevision($node, $newState, NULL, "{$oldState} ({$node->getRevisionId()}) => {$newState}", $default);
      }
      else {
        $workflowService->forceAssessmentState($node, $newState);
        $entity = $node;
      }
    }

    if ($underAssessmentRevisionOld instanceof Node) {
      \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($underAssessmentRevisionOld->getRevisionId());
    }

    $nodeForm->setEntity($entity);
    $form_state->setFormObject($nodeForm);
    $currentUser = \Drupal::currentUser();

    $message = t('The assessment "%assessment" was successfully updated.', ['%assessment' => $entity->getTitle()]);
    if (in_array($currentUser->id(), [$node->field_references_reviewer->target_id, $node->field_assessor->target_id])) {
      $message = t('The assessment "%assessment" was successfully submitted!', ['%assessment' => $entity->getTitle()]);
    }

    \Drupal::messenger()->addMessage($message);
  }

  private static function changeWorkflowButtons(&$form, AccountProxyInterface $currentUser) {
    if (!empty($form['actions']['workflow_assessment_finished_reviewing']['#access'])) {
      $form['actions']['workflow_assessment_finished_reviewing']['#value'] = t('Submit review');
    }
  }
}
