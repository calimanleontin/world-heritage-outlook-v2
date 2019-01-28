<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalParagraphDiffForm extends IucnModalDiffForm {

  /** @var \Drupal\Core\Entity\EntityFormBuilderInterface */
  protected $entityFormBuilder;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\Core\Entity\ContentEntityStorageInterface */
  protected $paragraphStorage;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\node\NodeInterface|null */
  protected $nodeRevision;

  /** @var \Drupal\paragraphs\ParagraphInterface */
  protected $paragraphRevision;

  /** @var string|null */
  protected $field;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null */
  protected $paragraphFormDisplay;

  /** @var array */
  protected $paragraphFormComponents = [];

  /** @var string[] */
  protected $fieldWidgetTypes = [];

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');
    $this->workflowService = $assessmentWorkflow;

    $routeMatch = $this->getRouteMatch();
    $this->field = $routeMatch->getParameter('field');
    $this->paragraphRevision = $routeMatch->getParameter('paragraph_revision');
    $this->nodeRevision = $routeMatch->getParameter('node_revision');

    // We want to render the diff forms using the form widget configured for the
    // parent entity.
    $this->paragraphFormDisplay = $this->entityFormDisplay->load("{$this->paragraphRevision->getEntityTypeId()}.{$this->paragraphRevision->bundle()}.default");
    $this->paragraphFormComponents = $this->paragraphFormDisplay->getComponents();
    uasort($this->paragraphFormComponents, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('iucn_assessment.workflow')
    );
  }

  public function getParagraphRevisionFromParentEntity(NodeInterface $parentEntity) {
    foreach ($parentEntity->get($this->field)->getValue() as $value) {
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
          'copy' => $this->getCopyValueButton($vid, $this->fieldWidgetTypes[$fieldName], $fieldName, $revision->get($fieldName)
            ->getValue()),
          'widget_type' => $this->fieldWidgetTypes[$fieldName],
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
              ? $this->getCopyValueButton(0, $this->fieldWidgetTypes[$fieldName], $fieldName, $initialValue)
              : NULL,
            'widget_type' => $this->fieldWidgetTypes[$fieldName],
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
      '#attributes' => ['class' => ['field-diff-table']],
    ];
    $finalRow = [
      'author' => $this->t('Final version'),
    ];
    foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
      $this->fieldWidgetTypes[$fieldName] = $this->getDiffFieldWidgetType($form[$fieldName]['widget']);
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
          $row[$field] = ['data' => ['#markup' => $diffData]];
          continue;
        }
        elseif (!empty($diffData['input'])) {
          $row[$field] = $diffData['input'];
          $row[$field]['#attributes']['class'][] = 'widget-type--' . $diffData['widget_type'];
        }
        elseif (!empty($diffData['markup'])) {
          $row[$field] = [
            'data' => [
              '#type' => 'table',
              '#rows' => $diffData['markup'],
              '#attributes' => [
                'class' => [
                  'relative',
                  'diff-context-wrapper',
                  'widget-type--' . $diffData['widget_type'],
                ],
              ],
            ],
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
