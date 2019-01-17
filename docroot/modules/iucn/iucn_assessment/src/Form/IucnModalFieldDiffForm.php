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
    $fieldDiff = [
      0 => [
        'author' => $this->t('Initial version'),
      ],
    ];
    $settings = json_decode($node->field_settings->value, TRUE);

    foreach ($settings['diff'] as $vid => $diff) {
      if (empty($diff['node'][$node->id()]['diff'][$fieldName])) {
        continue;
      }

      $rowDiff = $diff['node'][$node->id()];
      if (empty($initialValue)) {
        $initialRevision = $this->workflowService->getAssessmentRevision($rowDiff['initial_revision_id']);
        $initialValue = $initialRevision->get($fieldName)->getValue();
        $renderedInitialValue = $initialRevision->get($fieldName)->view(['settings' => ['link' => 0]]);
        $fieldDiff[0]['markup'] = [[['data' => $renderedInitialValue]]];
        $fieldDiff[0]['copy'] = $init_button = $this->getCopyValueButton(0, $fieldType, $fieldName, $initialValue);
      }

      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $this->workflowService->getAssessmentRevision($vid);
      $fieldDiff[] = [
        'author' => ($node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW)
          ? $node->field_assessor->entity->getDisplayName()
          : $revision->getRevisionUser()->getDisplayName(),
        'markup' => $this->getDiffMarkup($rowDiff['diff'][$fieldName]),
        'copy' => $this->getCopyValueButton($vid, $fieldType, $fieldName, $revision->get($fieldName)->getValue()),
      ];
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

    $diffTable = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $form[$this->field]['widget'][0]['value']['#title']],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];

    $fieldDiff = $this->getNodeFieldDiff($this->nodeRevision, $this->field, $this->getDiffFieldType($form[$this->field]['widget']));
    foreach ($fieldDiff as $diff) {
      $diffTable['#rows'][] = [
        'author' => $this->getTableCellMarkup($diff['author'], 'author', 2, -100),
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

    $diffTable[] = [
      'author' => $this->getTableCellMarkup($this->t('Final version'), 'author', 2, -100),
      'diff' => $form[$this->field],
    ];

    $form[$this->field] = $diffTable;
    self::hideUnnecessaryFields($form);
    self::buildCancelButton($form);
    return $form;
  }

}
