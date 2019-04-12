<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;

class IucnModalParagraphDiffForm extends IucnModalDiffForm {

  /** @var \Drupal\Core\Entity\ContentEntityStorageInterface */
  protected $paragraphStorage;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null */
  protected $paragraphFormDisplay;

  /** @var array */
  protected $paragraphFormComponents = [];

  /** @var string[] */
  protected $fieldWidgetTypes = [];

  /** @var string[] */
  protected $fieldWithDifferences = [
    'author', // Revision author is always rendered.
  ];

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, PrivateTempStoreFactory $temp_store_factory = NULL, AssessmentWorkflow $assessmentWorkflow = NULL, LanguageManagerInterface $languageManager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $entity_form_builder, $entityTypeManager, $temp_store_factory, $assessmentWorkflow, $languageManager);
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    // We want to render the diff forms using the form widget configured for the
    // parent entity.
    $this->paragraphFormDisplay = $this->entityFormDisplay->load("{$this->paragraphRevision->getEntityTypeId()}.{$this->paragraphRevision->bundle()}.{$this->formDisplayMode}");
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

  function isDeletedField($fieldDiff) {
    foreach ($fieldDiff as $rows) {
      foreach ($rows as $line) {
        if (is_array($line) && !is_array($line['data'])) {
          if ($line['data'] == '+') {
            return FALSE;
          }
        }
      }
    }
    return TRUE;
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
          : $assessmentRevision->getRevisionUser()->getDisplayName(),
      ];
      $deletedLine = TRUE;
      foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
        if (empty($rowDiff['diff'][$fieldName])) {
          continue;
        }
        $field = $revision instanceof ParagraphInterface
          ? $revision->get($fieldName)
          : NULL;
        $fieldValue = !empty($field) ? $field->getValue() : [];
        if (!$this->isDeletedField($rowDiff['diff'][$fieldName])) {
          $deletedLine = FALSE;
        }

        $row[$fieldName] = [
          'markup' => $this->getDiffMarkup($rowDiff['diff'][$fieldName]),
          'copy' => $this->getCopyValueButton($vid, $this->fieldWidgetTypes[$fieldName], $fieldName, $fieldValue),
          'widget_type' => $this->fieldWidgetTypes[$fieldName],
        ];
        $this->fieldWithDifferences[] = $fieldName;
      }

      if ($deletedLine) {
        $row = ['author' => $row['author'], 'deleted' => $this->t('This row has been deleted')];
      }

      $paragraphDiff[] = $row;

      if (empty($initialRevision)) {
        $initialAssessmentRevision = $this->workflowService->getPreviousWorkflowRevision($assessmentRevision);
        // All revisions have the same initial version.
        /** @var \Drupal\paragraphs\ParagraphInterface $initialRevision */
        $initialRevision = $this->getParagraphRevisionFromParentEntity($initialAssessmentRevision);
        foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
          if ($initialRevision instanceof ParagraphInterface) {
            $initialValue = $initialRevision->get($fieldName)->getValue();
            $renderedInitialValue = $initialRevision->get($fieldName)->view('diff');
            $renderedInitialValue['#title'] = NULL;
          }
          else {
            $initialValue = NULL;
            $renderedInitialValue = '';
          }
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
      '#weight' => 10,
      '#attributes' => ['class' => ['diff-table']],
      '#prefix' => '<div class="double-scrollbar-helper"><div class="inner"></div></div><div class="responsive-wrapper">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    ];
    $finalRow = [
      'author' => $this->t('Final version'),
    ];
    foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
      if (empty($this->paragraphRevision->{$fieldName})) {
        unset($this->paragraphFormComponents[$fieldName]);
        continue;
      }
      $this->fieldWidgetTypes[$fieldName] = $this->getDiffFieldWidgetType($form, $fieldName);
      $diffTable['#header'][$fieldName] = [
        'data' => $this->paragraphRevision->{$fieldName}->getFieldDefinition()->getLabel(),
        'class' => ['widget-type--' . Html::cleanCssIdentifier($this->getDiffFieldWidgetType($form, $fieldName))]
      ];
      $finalRow[$fieldName]['input'] = $form[$fieldName];
    }

    $paragraphDiff = $this->getParagraphDiff();

    $dependentFieldsList = [
      'field_as_threats_in' => ['field_as_threats_out', 'field_as_threats_extent'],
      'field_as_threats_out' => ['field_as_threats_in', 'field_as_threats_extent'],
      'field_as_threats_values_wh' => ['field_as_threats_values_bio'],
      'field_as_threats_values_bio' => ['field_as_threats_values_wh'],
      'field_as_legality' => ['field_as_threats_categories'],
      'field_as_targeted_species' => ['field_as_threats_categories'],
      'field_invasive_species_names' => ['field_as_threats_categories'],
    ];
    foreach ($dependentFieldsList as $fieldName => $dependentFields) {
      if (in_array($fieldName, $this->fieldWithDifferences)) {
        // These fields need to be rendered together. So, if at least one of them
        // was modified, we render both of them.
        $this->fieldWithDifferences = array_unique(array_merge($this->fieldWithDifferences, $dependentFields));
      }
    }

    foreach ($this->paragraphFormComponents as $fieldName => $widgetSettings) {
      if (!in_array($fieldName, $this->fieldWithDifferences)) {
        unset($diffTable['#header'][$fieldName]);
        unset($finalRow[$fieldName]);
      }
    }

    if (count($this->fieldWithDifferences) <= count($this->paragraphFormComponents)) {
      $form['info'] = [
        '#type' => 'markup',
        '#markup' => sprintf('<div class="messages messages--info"><div class="pull-left"><span>%s</span></div><div class="pull-right"><span class="legend-content-removed">%s</span><span class="legend-content-added">%s</span></div></div>',
          $this->t('The table below contains only fields which were modified by other user(s). For editing other fields, use the default row edit popup.'),
          $this->t('Content removed'),
          $this->t('Content added')),
        '#weight' => -100,
      ];
    }

    $paragraphDiff['edit'] = $finalRow;

    foreach ($paragraphDiff as $key => $diff) {
      $row = [];
      if (!empty($diff["deleted"])) {
        $field = 'author';
        $row[$field] = [
          'data' => ['#markup' => $diff[$field]],
          '#wrapper_attributes' => ['class' => ['field-name--' . $field]],
        ];
        $field = 'deleted';
        $row[$field] = [
          'data' => ['#markup' => $diff[$field]],
          '#wrapper_attributes' => ['class' => ['diff-deletedline'], 'colspan' => count($this->fieldWithDifferences) - 1],
        ];
        $diffTable[$key] = $row;
        continue;
      }
      foreach (array_merge(['author' => []], $this->paragraphFormComponents) as $field => $widgetSettings) {
        if (!in_array($field, $this->fieldWithDifferences)) {
          continue;
        }

        if (empty($diff[$field])) {
          $row[$field] = [
            'data' => ['#markup' => ''],
            '#wrapper_attributes' => ['class' => ['field-name--' . $field]],
          ];
          continue;
        }

        $diffData = $diff[$field];
        if (!is_array($diffData)) {
          $row[$field] = [
            'data' => ['#markup' => $diffData],
            '#wrapper_attributes' => ['class' => ['field-name--' . $field]],
          ];
          continue;
        }

        $cssClass = ' field-name--' . $field;
        if (!empty($diffData['widget_type'])) {
          $cssClass .= ' widget-type--' . $diffData['widget_type'];
        }
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
      }

      if (!empty($form['diff']['edit'][$originalField])) {
        $widget = $form[$field];
        unset($widget['#title']);
        unset($widget["{$originalField}_select"]['#title']);
        unset($widget['widget']['#title']);
        unset($widget['widget'][0]['#title']);
        unset($widget['widget'][0]['value']['#title']);
        $form['diff']['edit'][$originalField] = $widget;
      }

      unset($form[$field]);
      unset($form[$originalField]);
    }
  }

}
