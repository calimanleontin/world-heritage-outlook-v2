<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class IucnModalParagraphDiffForm extends IucnModalDiffForm {

  /** @var \Drupal\Core\Entity\ContentEntityStorageInterface */
  protected $paragraphStorage;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null */
  protected $paragraphFormDisplay;

  /** @var array */
  protected $paragraphFormComponents = [];

  /** @var string[] */
  protected $fieldWidgetTypes = [];

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, PrivateTempStoreFactory $temp_store_factory = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $entity_form_builder, $entityTypeManager, $temp_store_factory, $assessmentWorkflow);
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    // We want to render the diff forms using the form widget configured for the
    // parent entity.
    $this->paragraphFormDisplay = $this->entityFormDisplay->load("{$this->paragraphRevision->getEntityTypeId()}.{$this->paragraphRevision->bundle()}.default");
    $this->paragraphFormComponents = $this->paragraphFormDisplay->getComponents();
    uasort($this->paragraphFormComponents, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
  }

  public function getParagraphRevisionFromParentEntity(NodeInterface $parentEntity) {
    foreach ($parentEntity->get($this->fieldName)->getValue() as $value) {
      if (!empty($value['target_id']) && $value['target_id'] == $this->paragraphRevision->id()) {
        return $this->paragraphStorage->loadRevision($value['target_revision_id']);
      }
    }
    return NULL;
  }

  public function getParagraphDiff() {
    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);
    if (empty($settings['diff'])) {
      return [];
    }

    $paragraphDiff = [
      0 => [
        'author' => $this->t('Initial version'),
      ],
    ];

    foreach ($settings['diff'] as $vid => $diff) {
      if (empty($diff['paragraph'][$this->paragraphRevision->id()]['diff'])) {
        continue;
      }

      $assessmentRevision = $this->workflowService->getAssessmentRevision($vid);
      /** @var \Drupal\paragraphs\ParagraphInterface $revision */
      $revision = $this->getParagraphRevisionFromParentEntity($assessmentRevision);
      $rowDiff = $diff['paragraph'][$this->paragraphRevision->id()];

      $row = [
        'author' => ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW)
          ? $this->nodeRevision->field_assessor->entity->getDisplayName()
          : $revision->getRevisionUser()->getDisplayName(),
      ];
      foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
        if (empty($rowDiff['diff'][$fieldName])) {
          $row[$fieldName] = [];
          continue;
        }
        $row[$fieldName] = [
          'markup' => $this->getDiffMarkup($rowDiff['diff'][$fieldName]),
          'copy' => $this->getCopyValueButton($vid, $this->fieldNameWidgetTypes[$fieldName], $fieldName, $revision->get($fieldName)
            ->getValue()),
          'widget_type' => $this->fieldNameWidgetTypes[$fieldName],
        ];
      }
      $paragraphDiff[] = $row;

      if (empty($initialRevision)) {
        $initialAssessmentRevision = $this->workflowService->getPreviousWorkflowRevision($assessmentRevision);
        // All revisions have the same initial version.
        /** @var \Drupal\paragraphs\ParagraphInterface $initialRevision */
        $initialRevision = $this->getParagraphRevisionFromParentEntity($initialAssessmentRevision);

        foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
          $initialValue = $initialRevision->get($fieldName)->getValue();
          $renderedInitialValue = $initialRevision->get($fieldName)
            ->view(['settings' => ['link' => 0]]);
          unset($renderedInitialValue['#title']);
          $paragraphDiff[0][$fieldName] = [
            'markup' => [[['data' => $renderedInitialValue]]],
            'copy' => !empty($initialValue)
              ? $this->getCopyValueButton(0, $this->fieldNameWidgetTypes[$fieldName], $fieldName, $initialValue)
              : NULL,
            'widget_type' => $this->fieldNameWidgetTypes[$fieldName],
          ];
        }
      }
    }
    return $paragraphDiff;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $diffTable = [
      '#type' => 'table',
      '#header' => ['author' => $this->t('Author')],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['diff-table']],
      '#tree' => FALSE,
    ];
    $finalRow = [
      'author' => $this->t('Final version'),
    ];
    foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
      $this->fieldNameWidgetTypes[$fieldName] = $this->getDiffFieldWidgetType($form[$fieldName]['widget']);
      $diffTable['#header'][$fieldName] = $this->paragraphRevision->{$fieldName}->getFieldDefinition()
        ->getLabel();
      $finalRow[$fieldName]['input'] = $form[$fieldName];
    }

    $paragraphDiff = $this->getParagraphDiff();
    $paragraphDiff['edit'] = $finalRow;

    foreach ($paragraphDiff as $key => $diff) {
      $row = [];
      foreach ($diff as $field => $diffData) {
        if (!is_array($diffData)) {
          $row[$field] = [
            'data' => ['#markup' => $diffData],
            '#wrapper_attributes' => ['class' => ['field-name--' . $field]],
          ];
          continue;
        }

        $cssClass = 'widget-type--' . $diffData['widget_type'] . ' field-name--' . $field;
        if (!empty($diffData['input'])) {
          $row[$field] = $diffData['input'];
          $row[$field]['#wrapper_attributes']['class'][] = $cssClass;
        }
        elseif (!empty($diffData['markup'])) {
          $row[$field] = [
            'data' => [
              '#type' => 'table',
              '#rows' => $diffData['markup'],
              '#tree' => FALSE,
            ],
            '#wrapper_attributes' => ['class' => [$cssClass]],
          ];
        }
        else {
          $row[$field] = [];
        }

        if (!empty($diffData['copy'])) {
          $row[$field]['data']['#prefix'] = '<div class="diff-wrapper">';
          $row[$field]['data']['#suffix'] = $diffData['copy'] . '</div>';
        }
      }
      $diffTable[$key] = $row;
    }

    $form['diff'] = $diffTable;
    return $form;
  }

  public static function alter(array &$form, FormStateInterface $form_state) {
    // We need to move form field to the last row of the table after the form
    // has been altered by other modules / classes (e.g. ParagraphAsSiteThreatForm::alter)
    foreach (Element::children($form) as $field) {
      $originalField = $field;
      if (!preg_match('/^field\_/', $field) || empty($form[$field])) {
        continue;
      }
      elseif (preg_match('/(field\_.+)\_select\_wrapper/', $field, $matches)) {
        // See ParagraphAsSiteThreatForm::alter to understand this code block.
        $originalField = $matches[1];
        $form['diff']['edit'][$originalField] = $form[$field];
        unset($form[$field]);
        unset($form[$originalField]);
      }
      else {
        $form['diff']['edit'][$field] = $form[$field];
        unset($form[$field]);
      }
      unset($form['diff']['edit'][$originalField]['#title']);
      unset($form['diff']['edit'][$originalField]["{$originalField}_select"]['#title']);
      unset($form['diff']['edit'][$originalField]['widget']['#title']);
      unset($form['diff']['edit'][$originalField]['widget'][0]['#title']);
      unset($form['diff']['edit'][$originalField]['widget'][0]['value']['#title']);
    }
  }

}
