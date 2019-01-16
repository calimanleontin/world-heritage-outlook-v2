<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalFieldDiffForm extends IucnModalForm {

  use DiffModalTrait;
  use AssessmentEntityFormTrait;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\node\NodeInterface|null */
  protected $nodeRevision;

  /** @var string|null  */
  protected $field;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null  */
  protected $nodeFormDisplay;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');
    $this->workflowService = $assessmentWorkflow;

    $routeMatch = $this->getRouteMatch();
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->field = $routeMatch->getParameter('field');

    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
      // Remove all other fields except the selected one.
      if ($name != $this->field) {
        $this->nodeFormDisplay->removeComponent($name);
      }
    }
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

  public function getNodeFieldDiff(NodeInterface $node, $fieldName, $fieldType) {
    $fieldDiff = [];
    $settings = json_decode($node->field_settings->value, TRUE);

    foreach ($settings['diff'] as $vid => $diff) {
      if (empty($diff['node'][$node->id()]['diff'][$fieldName])) {
        continue;
      }
      $row = [];
      $rowDiff = $diff['node'][$node->id()];
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $this->workflowService->getAssessmentRevision($vid);
      $row['author'] = $node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW
        ? $node->field_assessor->entity->getDisplayName()
        : $revision->getRevisionUser()->getDisplayName();

      $row['diff'] = ['data' => []];

      if (!empty($rowDiff['initial_revision_id'])) {
        $initial_revision = $this->workflowService->getAssessmentRevision($rowDiff['initial_revision_id']);
        $data_value = $node->get($fieldName)->getValue();
        $data_value_0 = $initial_revision->get($fieldName)->getValue();
        $value_0 = $initial_revision->get($fieldName)->view(['settings' => ['link' => 0]]);
      }
      elseif ($node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        $data_value = $node->get($fieldName)->getValue();
        $data_value_0 = $revision->get($fieldName)->getValue();
        $value_0 = $revision->get($fieldName)->view(['settings' => ['link' => 0]]);
      }
      else {
        $data_value_0 = $node->get($fieldName)->getValue();
        $value_0 = $node->get($fieldName)->view(['settings' => ['link' => 0]]);
        $data_value = $revision->get($fieldName)->getValue();
      }
      unset($value_0['#title']);
      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $this->getDiffMarkup($rowDiff['diff'][$fieldName]),
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        '#prefix' => '<div class="diff-wrapper">',
        '#suffix' => $this->getCopyValueButton($vid, $fieldType, $fieldName, $data_value) . '</div>',
      ];
      $diff_table['#rows'][] = $row;

      if (empty($diff_table['#rows'][0]['diff']['data'])) {
        $init_button = $this->getCopyValueButton(0, $fieldType, $fieldName, $data_value_0);
        $diff_table['#rows'][0]['diff']['data'] = $value_0;
        $diff_table['#rows'][0]['diff']['data']['#prefix'] = '<div class="diff-wrapper">';
        $diff_table['#rows'][0]['diff']['data']['#suffix'] = $init_button . '</div>';
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
    $form['#prefix'] = '<div id="drupal-modal" class="diff-modal">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';

    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);

    $diff_table = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $form[$this->field]['widget'][0]['value']['#title']],
      '#rows' => [
        [
          'author' => $this->getTableCellMarkup($this->t('Initial version'), 'author', 2, -100),
        ]
      ],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];

    $fieldDiff = $this->getNodeFieldDiff($this->nodeRevision, $this->field, $this->getDiffFieldType($form[$this->field]['widget']));

    foreach ($settings['diff'] as $assessment_vid => $diff) {
      if (empty($diff['node'][$this->nodeRevision->id()]['diff'][$this->field])) {
        continue;
      }
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $this->workflowService->getAssessmentRevision($assessment_vid);
      $diff_data = $diff['node'][$this->nodeRevision->id()]['diff'][$this->field];
      $row = [];
      $row['author'] = $this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW
        ? $this->nodeRevision->field_assessor->entity->getDisplayName()
        : $revision->getRevisionUser()->getDisplayName();

      $row['diff'] = ['data' => []];
      $diff_rows = $this->getDiffMarkup($diff_data);

      if (!empty($diff['node'][$this->nodeRevision->id()]['initial_revision_id'])) {
        $initial_revision = $this->workflowService->getAssessmentRevision($diff['node'][$this->nodeRevision->id()]['initial_revision_id']);
        $data_value = $this->nodeRevision->get($this->field)->getValue();
        $data_value_0 = $initial_revision->get($this->field)->getValue();
        $value_0 = $initial_revision->get($this->field)->view(['settings' => ['link' => 0]]);
      }
      elseif ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        $data_value = $this->nodeRevision->get($this->field)->getValue();
        $data_value_0 = $revision->get($this->field)->getValue();
        $value_0 = $revision->get($this->field)->view(['settings' => ['link' => 0]]);
      }
      else {
        $data_value_0 = $this->nodeRevision->get($this->field)->getValue();
        $value_0 = $this->nodeRevision->get($this->field)->view(['settings' => ['link' => 0]]);
        $data_value = $revision->get($this->field)->getValue();
      }
      unset($value_0['#title']);
      $type = $this->getDiffFieldType($form, $this->field);
      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $diff_rows,
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        '#prefix' => '<div class="diff-wrapper">',
        '#suffix' => $this->getCopyValueButton($assessment_vid, $type, $this->field, $data_value) . '</div>',
      ];
      $diff_table['#rows'][] = $row;

      if (empty($diff_table['#rows'][0]['diff']['data'])) {
        $init_button = $this->getCopyValueButton(0, $type, $this->field, $data_value_0);
        $diff_table['#rows'][0]['diff']['data'] = $value_0;
        $diff_table['#rows'][0]['diff']['data']['#prefix'] = '<div class="diff-wrapper">';
        $diff_table['#rows'][0]['diff']['data']['#suffix'] = $init_button . '</div>';
      }
    }
    $diff_table[] = [
      'author' => $this->getTableCellMarkup($this->t('Final version'), 'author', 2, -100),
      'diff' => $form[$this->field],
    ];

    $form['diff'] = $diff_table;
    unset($form[$this->field]);
    self::hideUnnecessaryFields($form);
    self::buildCancelButton($form);
    return $form;
  }

}
