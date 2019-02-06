<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;

class IucnModalFieldDiffForm extends IucnModalDiffForm {

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
        $renderedInitialValue = $initialRevision->get($this->fieldName)->view('diff');
        $renderedInitialValue['#title'] = NULL;
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
      '#header' => [$this->t('Author'), $form[$this->fieldName]['widget']['#title']],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['diff-table']],
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

  /**
   * {@inheritdoc}
   */
  public function ajaxSave(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $formObject */
    $formObject = $form_state->getFormObject();
    $this->nodeRevision = $formObject->getEntity();
    return parent::ajaxSave($form, $form_state);
  }
}
