<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
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

  /** @var string|null  */
  protected $field;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null  */
  protected $paragraphFormDisplay;

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

  public function getParagraphDiff(NodeInterface $node, ParagraphInterface $paragraph) {
    $settings = json_decode($node->field_settings->value, TRUE);
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
        'author' => ($node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW)
            ? $node->field_assessor->entity->getDisplayName()
            : $revision->getRevisionUser()->getDisplayName()
      ];
      foreach ($this->paragraphFormDisplay->getComponents() as $fieldName => $fieldSettings) {
        if (empty($rowDiff['diff'][$fieldName])) {
          $row[$fieldName] = [];
          continue;
        }
        $fieldType = $revision->get($fieldName)->getFieldDefinition()->getType();
        $row[$fieldName] = [
          'markup' => $this->getDiffMarkup($rowDiff['diff'][$fieldName]),
          'copy' => $this->getCopyValueButton($vid, $fieldType, $fieldName, $revision->get($fieldName)->getValue()),
        ];
      }
      $paragraphDiff[] = $row;


      if (empty($initialRevision)) {
        // All revisions have the same initial version.
        /** @var \Drupal\paragraphs\ParagraphInterface $initialRevision */
        $initialRevision = $this->paragraphStorage->loadRevision($rowDiff['initial_revision_id']);
        foreach ($this->paragraphFormDisplay->getComponents() as $fieldName => $fieldSettings) {
          $fieldType = $revision->get($fieldName)->getFieldDefinition()->getType();
          $initialValue = $initialRevision->get($fieldName)->getValue();
          $renderedInitialValue = $initialRevision->get($fieldName)->view(['settings' => ['link' => 0]]);
          unset($renderedInitialValue['#title']);
          $paragraphDiff[0][$fieldName] = [
            'markup' => [[['data' => $renderedInitialValue]]],
            'copy' => !empty($initialValue)
              ? $this->getCopyValueButton(0, $fieldType, $fieldName, $initialValue)
              : NULL,
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

    $paragraphDiff = $this->getParagraphDiff($this->nodeRevision, $this->paragraphRevision);
    $finalRow = [
      'author' => $this->t('Final version'),
    ];
    foreach ($this->paragraphFormDisplay->getComponents() as $fieldName => $fieldSettings) {
      $diffTable['#header'][$fieldName] = $this->paragraphRevision->{$fieldName}->getFieldDefinition()->getLabel();
      $finalRow[$fieldName]['input'] = $form[$fieldName];
      unset($form[$fieldName]);
    }
    $paragraphDiff['edit'] = $finalRow;

    foreach ($paragraphDiff as $key => $diff) {
      $row = [];
      foreach ($diff as $field => $diffData) {
        if (!is_array($diffData)) {
          $row[$field] = $this->getTableCellMarkup($diffData, $field, 2, -100);
          continue;
        }
        elseif (!empty($diffData['input'])) {
          $row[$field] = $diffData['input'];
        }
        elseif (!empty($diffData['markup'])) {
          $row[$field] = [
            'data' => [
              '#type' => 'table',
              '#rows' => $diffData['markup'],
              '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
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

}
