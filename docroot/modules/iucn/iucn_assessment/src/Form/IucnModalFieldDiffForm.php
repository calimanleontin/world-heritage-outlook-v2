<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;

class IucnModalFieldDiffForm extends IucnModalDiffForm {

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null  */
  protected $nodeFormDisplay;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $entity_form_builder, $entityTypeManager, $assessmentWorkflow);

    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
      // Remove all other fields except the selected one.
      if ($name != $this->fieldName) {
        $this->nodeFormDisplay->removeComponent($name);
      }
    }
  }

  public function getNodeFieldDiff($fieldWidgetType) {
    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);
    if (empty($settings['diff'])) {
      return [];
    }

    $fieldDiff = [
      0 => [
        'author' => $this->t('Initial version'),
      ],
    ];

    foreach ($settings['diff'] as $vid => $diff) {
      if (empty($diff['node'][$this->nodeRevision->id()]['diff'][$this->fieldName])) {
        continue;
      }

      $rowDiff = $diff['node'][$this->nodeRevision->id()];
      $revision = $this->workflowService->getAssessmentRevision($vid);
      $fieldDiff[] = [
        'author' => ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW)
          ? $this->nodeRevision->field_assessor->entity->getDisplayName()
          : $revision->getRevisionUser()->getDisplayName(),
        'markup' => $this->getDiffMarkup($rowDiff['diff'][$this->fieldName]),
        'copy' => $this->getCopyValueButton($vid, $fieldWidgetType, $this->fieldName, $revision->get($this->fieldName)->getValue()),
      ];

      if (empty($initialValue)) {
        // All revisions have the same initial version.
        $initialRevision = $this->workflowService->getAssessmentRevision($rowDiff['initial_revision_id']);
        $initialValue = $initialRevision->get($this->fieldName)->getValue();
        $renderedInitialValue = $initialRevision->get($this->fieldName)->view(['settings' => ['link' => 0]]);
        unset($renderedInitialValue['#title']);
        $fieldDiff[0]['markup'] = [[['data' => $renderedInitialValue]]];
        $fieldDiff[0]['copy'] = $init_button = $this->getCopyValueButton(0, $fieldWidgetType, $this->fieldName, $initialValue);
      }
    }
    return $fieldDiff;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->init($form_state);
    $this->setFormDisplay($this->nodeFormDisplay, $form_state);

    $form = parent::buildForm($form, $form_state);

    $diffTable = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $form[$this->fieldName]['widget'][0]['value']['#title']],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];

    $fieldDiff = $this->getNodeFieldDiff($this->getDiffFieldWidgetType($form[$this->fieldName]['widget']));
    foreach ($fieldDiff as $diff) {
      $diffTable['#rows'][] = [
        'author' => ['data' => ['#markup' => $diff['author']]],
        'diff' => [
          'data' => [
            '#type' => 'table',
            '#rows' => $diff['markup'],
            '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
            '#prefix' => '<div class="diff-wrapper">',
            '#suffix' => $diff['copy'] . '</div>',
          ],
        ],
      ];
    }

    unset($form[$this->fieldName]['#title']);
    unset($form[$this->fieldName]['widget']['#title']);
    unset($form[$this->fieldName]['widget'][0]['#title']);
    unset($form[$this->fieldName]['widget'][0]['value']['#title']);
    $diffTable[] = [
      'author' => ['data' => ['#markup' => $this->t('Final version')]],
      'diff' => $form[$this->fieldName],
    ];

    $form[$this->fieldName] = $diffTable;
    return $form;
  }

}
