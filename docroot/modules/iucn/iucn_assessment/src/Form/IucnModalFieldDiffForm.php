<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Controller\ModalDiffController;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalFieldDiffForm extends IucnModalForm {

  use DiffModalTrait;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\node\NodeInterface|null */
  protected $nodeRevision;

  /** @var string|null  */
  protected $field;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');
    $this->workflowService = $assessmentWorkflow;

    $routeMatch = $this->getRouteMatch();
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->field = $routeMatch->getParameter('field');
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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);
    /** @var \Drupal\field\FieldConfigInterface $fieldConfig */
    $fieldConfig = $this->nodeRevision->get($this->field)->getFieldDefinition();
    $parent_form = parent::buildForm($form, $form_state);

    $diff_table = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $fieldConfig->label()],
      '#rows' => [
        [
          'author' => $this->getTableCellMarkup($this->t('Initial version'), 'author', 2, -100),
        ]
      ],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];

    unset($parent_form[$this->field]['widget'][0]['value']['#title']);
    unset($parent_form[$this->field]['diff']);

    $form = [
      'actions' => $parent_form['actions'],
      '#prefix' => '<div id="drupal-modal" class="diff-modal">',
      '#suffix' => '</div>',
    ];


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
      $diff_rows = ModalDiffController::getDiffMarkup($diff_data);

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
      $type = $this->get_diff_field_type($parent_form, $this->field);
      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $diff_rows,
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        '#prefix' => '<div class="diff-wrapper">',
        '#suffix' => $this->get_copy_value_button($form, $type, $data_value, $this->field, $assessment_vid, $this->field) . '</div>',
      ];
      $diff_table['#rows'][] = $row;

      if (empty($diff_table['#rows'][0]['diff']['data'])) {
        $init_button = $this->get_copy_value_button($form, $type, $data_value_0, $this->field, 0, $this->field);
        $diff_table['#rows'][0]['diff']['data'] = $value_0;
        $diff_table['#rows'][0]['diff']['data']['#prefix'] = '<div class="diff-wrapper">';
        $diff_table['#rows'][0]['diff']['data']['#suffix'] = $init_button . '</div>';
      }
    }
    $diff_table[] = [
      'author' => $this->getTableCellMarkup($this->t('Final version'), 'author', 2, -100),
      'diff' => $parent_form[$this->field],
    ];

    $form['diff'] = $diff_table;
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';

    self::buildCancelButton($form);
    unset($form['actions']['delete']);
    return $form;
  }

}
