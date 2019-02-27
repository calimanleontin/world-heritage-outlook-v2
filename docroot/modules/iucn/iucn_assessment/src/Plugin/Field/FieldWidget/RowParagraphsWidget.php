<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'row_entity_reference_paragraphs' widget.
 *
 * @FieldWidget(
 *   id = "row_entity_reference_paragraphs",
 *   label = @Translation("Paragraphs row"),
 *   description = @Translation("A paragraphs row form widget."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class RowParagraphsWidget extends ParagraphsWidget implements ContainerFactoryPluginInterface {

  /** @var \Symfony\Component\HttpFoundation\Request */
  protected $request;

  /** @var \Drupal\Core\Routing\RouteMatchInterface */
  protected $routeMatch;

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Entity\ContentEntityStorageInterface */
  protected $paragraphStorage;

  /** @var \Drupal\paragraphs\ParagraphInterface */
  protected $lastProcessedParagraph;

  /**
   * The number of columns in the grid.
   *
   * @var int
   */
  protected $colCount;

  /**
   * The parent node.
   *
   * @var \Drupal\Node\NodeInterface
   */
  protected $parentNode;

  /**
   * The diff array.
   *
   * @var array
   */
  protected $diff;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, RequestStack $requestStack, RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->request = $requestStack->getCurrentRequest();
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'title' => t('Paragraph'),
      'title_plural' => t('Paragraphs'),
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'show_numbers' => 'no',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '',
      'features' => [],
      'empty_message' => '',
      'only_editable' => FALSE,
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $options = $this->getSettingOptions('show_numbers');
    $elements['show_numbers'] = [
      '#type' => 'select',
      '#title' => $this->t('Show numbers'),
      '#description' => $this->t('Show number column in table.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('show_numbers'),
      '#required' => TRUE,
    ];
    $elements['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty message'),
      '#description' => $this->t('Show a message when there are no paragraphs.'),
      '#default_value' => $this->getSetting('empty_message'),
    ];
    $elements['only_editable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove add/delete buttons.'),
      '#description' => $this->t('Make it impossible to add or delete paragraphs.'),
      '#default_value' => $this->getSetting('only_editable'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSettingOptions($setting_name) {
    $options = parent::getSettingOptions($setting_name);
    switch ($setting_name) {
      case 'show_numbers':
        $options = [
          'no' => $this->t('No'),
          'yes' => $this->t('Yes'),
        ];
        break;
    }
    return isset($options) ? $options : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $options = $this->getSettingOptions('show_numbers');
    $show_numbers = $options[$this->getSetting('show_numbers')];
    $empty_message = $this->getSetting('empty_message');
    $only_editable = $this->getSetting('only_editable');

    $summary[] = $this->t('Show numbers: @show_numbers', ['@show_numbers' => $show_numbers]);
    if (!empty($empty_message)) {
      $summary[] = $this->t('Empty message: @empty_message', ['@empty_message' => $empty_message]);
    }
    if (!empty($only_editable)) {
      $summary[] = $this->t('Paragraphs cannot be added or deleted');
    }

    return $summary;
  }

  /**
   * Check if a paragraph has any differences for the rendered fields.
   *
   * @param $paragraph_id
   * @param $rendered_fields
   *
   * @return bool
   */
  public function paragraphHasDifferences(ParagraphInterface $paragraph, array $fieldsToCheck = NULL) {
    if (empty($fieldsToCheck)) {
      $fieldsToCheck = array_keys($this->getFieldComponents($paragraph));
    }
    if (empty($this->diff)) {
      return FALSE;
    }
    foreach ($this->diff as $vid => $diff) {
      if (empty($diff['paragraph']) || !in_array($paragraph->id(), array_keys($diff['paragraph']))) {
        continue;
      }
      foreach (array_keys($diff['paragraph'][$paragraph->id()]['diff']) as $diff_field) {
        if (in_array($diff_field, $fieldsToCheck)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public function buildAddMoreAjaxButton(&$elements, $field_name) {
    $add_more_button = array_keys($elements['add_more'])[0];
    $target_paragraph = FieldConfig::loadByName('node', 'site_assessment', $field_name)
      ->getSetting('handler_settings')['target_bundles'];
    $bundle = reset($target_paragraph);
    if (!empty($this->parentNode->id())) {
      $tab = \Drupal::request()->query->get('tab');
      $title = ($tab != 'projects') ? $this->t('Add more') : $this->t('Add a project');
      $elements['add_more'][$add_more_button] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute('iucn_assessment.modal_paragraph_add', [
          'node' => $this->parentNode->id(),
          'node_revision' => $this->parentNode->getRevisionId(),
          'field' => $field_name,
          'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field_name) . '-wrapper',
          'bundle' => $bundle,
        ]),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
            'paragraphs-add-more-button',
          ],
          'data-dialog-type' => 'modal',
          'title' => $title,
        ],
      ];
    }
  }

  public function appendRevertParagraphAction(array &$paragraph_row, $paragraph_id, $field_name, $type) {
    if ($type == 'accept') {
      $icon = 'paragraphs-icon-button-accept';
      $title = $this->t('Accept');
      $paragraph = Paragraph::load($paragraph_id);
      /** @var User $author */
      $author = $paragraph->getRevisionAuthor();
      $author = $author->getDisplayName();
    }
    else {
      $icon = 'paragraphs-icon-button-revert';
      $title = $this->t('Revert');
    }

    $paragraph_row['actions']['actions']['revert'] = [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute('iucn_assessment.revert_paragraph', [
        'node' => $this->parentNode->id(),
        'node_revision' => $this->parentNode->getRevisionId(),
        'field' => $field_name,
        'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field_name) . '-wrapper',
        'paragraph' => $paragraph_id,
      ]),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
          'paragraphs-icon-button',
          $icon,
        ],
        'data-dialog-type' => 'modal',
        'title' => $title,
      ],
    ];
    if (!empty($author)) {
      $tooltip = $this->t('Added by: @author', ['@author' => $author]);
      $paragraph_row['actions']['actions']['revert']['#prefix'] = '<div class="paragraph-author">' . $tooltip . '</div>';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $fieldName = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];
    $widgetState = static::getWidgetState($parents, $fieldName, $form_state);

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $this->lastProcessedParagraph = $widgetState['paragraphs'][$delta]['entity'];
    $element['#paragraph_id'] = $this->lastProcessedParagraph->id();

    $element['top'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'paragraph-top',
          $this->paragraphHasDifferences($this->lastProcessedParagraph) ? 'paragraph-diff-row' : '',
        ],
      ],
      'summary' => $this->buildRow($this->lastProcessedParagraph),
      'actions' => $this->buildRowActions($this->lastProcessedParagraph),
    ];
    if (empty($this->colCount)) {
      $this->colCount = array_sum(array_column($element['top']['summary'], 'span')) + 1;
    }

//    $assessmentState = $this->parentNode->field_state->value;
//    if ($assessmentState == AssessmentWorkflow::STATUS_READY_FOR_REVIEW
//      && $this->isNewParagraph($this->parentNode, AssessmentWorkflow::STATUS_UNDER_EVALUATION, $fieldName, $this->lastProcessedParagraph->id())
//      && !$this->isNewParagraph($this->parentNode, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, $fieldName, $this->lastProcessedParagraph->id())) {
//      $element['top']['#attributes']['class'][] = "paragraph-new-row";
//    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $this->parentNode = $form_state->getFormObject()->getEntity();
    $settings = json_decode($this->parentNode->field_settings->value, TRUE);
    $this->diff = !empty($settings['diff']) ? $settings['diff'] : NULL;

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $elements[] = $this->buildHeaderRow();
    // Show deleted paragraphs.
    if ($this->parentNode->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
      $this->appendDeletedParagraphs($elements, $fieldName);
    }

    if ($this->parentNode->field_state->value == AssessmentWorkflow::STATUS_UNDER_COMPARISON) {
      $this->appendReviewerParagraphs($elements, $fieldName);
    }

    $fieldName = $this->fieldDefinition->getName();
    if (!empty($elements['add_more'])) {
      if (empty($this->getSetting('only_editable'))) {
        // Make the add more button open a modal.
        $this->buildAddMoreAjaxButton($elements, $fieldName);
      }
      else {
        $elements['add_more']['#access'] = FALSE;
      }
    }

    $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $elements['#attached']['library'][] = 'iucn_backend/font-awesome';
    $elements['#prefix'] = str_replace('paragraphs-tabs-wrapper', 'raw-paragraphs-tabs-wrapper', $elements['#prefix']);

    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      if (empty($element['top'])) {
        continue;
      }
      $element['top']['#attributes']['class'][] = "paragraph-top-col-{$this->colCount}";
      foreach (Element::children($element['top']) as $topKey) {
        $element['top'][$topKey] = $this->buildCellsContainers($element['top'][$topKey]);
      }
    }

    return $elements;
  }

  protected function buildCellsContainers($elements) {
    foreach ($elements as $field => $component) {
      if ($field[0] === '#') {
        continue;
      }
      $span = !empty($component['span']) ? $component['span'] : 1;
      unset($component['span']);
      $container = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'paragraph-summary-component',
            "paragraph-summary-component-$field",
            "paragraph-summary-component-span-$span",
          ],
        ],
      ];
      if (!empty($component['value'])) {
        $container['data']['#markup'] = is_array($component['value']) ? implode("\n", $component['value']) : $component['value'];
      }
      else {
        $container['data'] = $component;
      }
      if (!empty($component['class'])) {
        $container['#attributes']['class'][] = $component['class'];
      }
      if (!empty($component['#weight'])) {
        $container['#weight'] = $component['#weight'];
      }
      $elements[$field] = $container;
    }
    return $elements;
  }

  /**
   * Check if a paragraph is new compared to previous revisions of a certain
   * state.
   *
   * @param NodeInterface $node
   * @param $fieldName
   * @param $paragraph_id
   *
   * @return bool
   */
  public function isNewParagraph(NodeInterface $node, $state, $fieldName, $paragraph_id) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $revision = $assessment_workflow->getRevisionByState($node, $state);
    if (empty($revision)) {
      return FALSE;
    }
    return !in_array($paragraph_id, array_column($revision->get($fieldName)
        ->getValue(), 'target_id'))
      && in_array($paragraph_id, array_column($node->get($fieldName)
        ->getValue(), 'target_id'));
  }

  /**
   * Build the table header.
   *
   * @param $elements
   */
  public function buildHeaderRow() {
    // Use the last rendered paragraph to build the header based on it's fields.
    if (!empty($this->lastProcessedParagraph)) {
      $row = $this->getHeaderRow($this->lastProcessedParagraph);
      $row += ['actions' => $this->t('Actions')];
//      $header_containers = $this->getSummaryContainers($row);
//      foreach ($header_containers as &$container) {
//        $container['#attributes']['title'] = $container['data'];
//      }
//      $header_containers['actions']['#prefix'] = '<div class="paragraph-summary-component">';
//      $header_containers['actions']['#suffix'] = '</div>';
      return [
        '#weight' => -100,
        '#delta' => -100,
        'top' => [
          '#type' => 'container',
          '#weight' => -100,
          '#delta' => -100,
          '#attributes' => [
            'class' => [
              'paragraph-top',
              'paragraph-header',
            ],
          ],
          'summary' => $row,
        ],
      ];
    }

    // Show an empty message if the table is empty.
    $empty_message = $this->getSetting('empty_message');
    if (!empty($empty_message)) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'paragraph-summary-component',
            'paragraph-empty',
          ],
        ],
        'data' => ['#markup' => $empty_message],
      ];
    }
    return [];
  }

  public function getReviewerParagraphs($fieldName) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $current_revision = $this->parentNode;
    $reviewer_revisions = $assessment_workflow->getAllReviewersRevisions($current_revision);
    if (empty($reviewer_revisions)) {
      return NULL;
    }
    $reviewer_added_paragraphs = [];
    foreach ($reviewer_revisions as $reviewer_revision) {
      $current_revision_paragraphs = array_column($current_revision->get($fieldName)
        ->getValue(), 'target_id');
      $reviewer_revision_paragraphs = array_column($reviewer_revision->get($fieldName)
        ->getValue(), 'target_id');
      $added_paragraphs = array_diff($reviewer_revision_paragraphs, $current_revision_paragraphs);
      $reviewer_added_paragraphs = array_merge($reviewer_added_paragraphs, $added_paragraphs);
    }
    return $reviewer_added_paragraphs;
  }

  public function appendReviewerParagraphs(&$elements, $fieldName) {
    $reviewer_paragraphs = $this->getReviewerParagraphs($fieldName);
    $reviewer_paragraphs_rows = $this->getParagraphsRows($reviewer_paragraphs, $fieldName, 'paragraph-new-row');
    if (!empty($reviewer_paragraphs_rows)) {
      foreach ($reviewer_paragraphs_rows as $paragraph_id => &$reviewer_paragraph_row) {
        $this->appendRevertParagraphAction($reviewer_paragraph_row, $paragraph_id, $fieldName, 'accept');
        $reviewer_paragraph_row['_weight'] = [
          '#type' => 'weight',
          '#delta' => $this->realItemCount + 10,
          '#default_value' => $this->realItemCount + 10,
        ];
        $elements[] = $reviewer_paragraph_row;
      }
    }
  }

  public function getAssessorDeletedParagraphs($fieldName) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $current_revision = $this->parentNode;
    $under_evaluation_revision = $assessment_workflow->getRevisionByState($current_revision, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    if (empty($under_evaluation_revision)) {
      return NULL;
    }
    $assessor_deleted_paragraphs = $this->getDeletedParagraphs($current_revision, $under_evaluation_revision, $fieldName);
    $under_as_revision = $assessment_workflow->getRevisionByState($current_revision, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);
    if (empty($under_as_revision)) {
      return $assessor_deleted_paragraphs;
    }

    $coordinator_deleted_paragraphs = $this->getDeletedParagraphs($current_revision, $under_as_revision, $fieldName);
    return array_diff($assessor_deleted_paragraphs, $coordinator_deleted_paragraphs);
  }

  public function getDeletedParagraphs(NodeInterface $new_revision, NodeInterface $old_revision, $fieldName) {
    $new_revision_paragraphs = array_column($new_revision->get($fieldName)
      ->getValue(), 'target_id');
    $old_revision_paragraphs = array_column($old_revision->get($fieldName)
      ->getValue(), 'target_id');
    $deleted_paragraphs = array_diff($old_revision_paragraphs, $new_revision_paragraphs);
    return $deleted_paragraphs;
  }

  public function getParagraphsRows($paragraphs, $fieldName, $row_class = '') {
    $elements = [];
    if (!empty($paragraphs)) {
      foreach ($paragraphs as $paragraph) {
        $paragraphs_entity = Paragraph::load($paragraph);
        $row = $this->buildRow($paragraphs_entity);
        $elements[$paragraph] = [
          '#type' => 'container',
          'top' => ['summmary' => $row],
          'actions' => [
            '#type' => 'container',
            'actions' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['paragraphs-actions']],
            ],
            '#attributes' => [
              'class' => [
                'paragraph-summary-component',
              ],
            ],
          ],
          '#attributes' => [
            'class' => [
              'paragraph-top',
              'paragraph-top-add-above',
              'paragraph-no-tabledrag',
              $row_class,
            ],
          ],
        ];
      }
    }
    return $elements;
  }

  /**
   * Show the paragraphs deleted by the assessor.
   *
   * @param $elements
   * @param $fieldName
   */
  public function appendDeletedParagraphs(&$elements, $fieldName) {
    $deleted_paragraphs = $this->getAssessorDeletedParagraphs($fieldName);
    $deleted_paragraphs_rows = $this->getParagraphsRows($deleted_paragraphs, $fieldName, 'paragraph-deleted-row');
    if (!empty($deleted_paragraphs_rows)) {
      foreach ($deleted_paragraphs_rows as $paragraph_id => &$deleted_paragraph_row) {
        $this->appendRevertParagraphAction($deleted_paragraph_row, $paragraph_id, $fieldName, 'revert');
      }
      $elements += $deleted_paragraphs_rows;
    }
  }

  /**
   * Returns an array containing the components for the header.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   The components.
   */
  public function getHeaderRow(ParagraphInterface $paragraph) {
    $header = [];
    if ($this->getSetting('show_numbers') == 'yes') {
      $header['num'] = [
        'value' => $this->t('No.'),
        'span' => 1,
      ];
    }

    $columns = $this->buildRow($paragraph);
    foreach (array_keys($columns) as $fieldName) {
      $fieldColumn = $this->getFieldColumn($fieldName);
      $fieldDefinition = $paragraph->getFieldDefinition($fieldName);

      switch ($fieldColumn) {
        case 'field_as_benefits_category':
          $label = $this->t('Benefit type');
          break;

        case 'field_as_benefits_category_child_category':
          $label = $this->t('Specific benefits');
          break;

        case 'field_as_threats_categories_child_category':
          $label = $this->t('Subcategories');
          break;

        case 'field_as_threats_values_wh':
        case 'field_as_threats_values_bio':
          $label = $this->t('WH values');
          break;

        case 'other_information':
          $label = $this->t('Other information');
          break;

        case 'negative_factors':
          $label = $this->t('Factors negatively affecting provision of benefits');
          break;

        default:
          $label = $fieldDefinition->getLabel();
      }

      $header[$fieldColumn]['value'] = $label;
      $header[$fieldColumn]['span'] = $this->getFieldSpan($fieldDefinition);
    }

    $header += [
      'actions' => [
        'value' => $this->t('Actions'),
        'span' => 1,
      ],
    ];
    return $header;
  }

  /**
   * Returns the field components for the default display view of a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   The field components.
   */
  public function getFieldComponents(ParagraphInterface $paragraph) {
    $form_display_mode = $this->getSetting('form_display_mode');
    $bundle = $paragraph->getType();
    $entityFormDisplay = EntityFormDisplay::load("paragraph.$bundle.$form_display_mode");
    if (empty($entityFormDisplay)) {
      $entityFormDisplay = EntityFormDisplay::load("paragraph.$bundle.default");
    }
    $components = $entityFormDisplay->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    return $components;
  }

  /**
   * Returns the render array with the paragraph table row.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function buildRow(ParagraphInterface $paragraph) {
    $row = [];

    static $num = 0;
    if ($this->getSetting('show_numbers') == 'yes') {
      $num += 1;
      $row['num']['value'] = $num;
    }

    $components = $this->getFieldComponents($paragraph);
    foreach (array_keys($components) as $fieldName) {
      $fieldDefinition = $paragraph->getFieldDefinition($fieldName);
      if (!($fieldDefinition instanceof FieldConfigInterface)
        || $paragraph->get($fieldName)->access('view') == FALSE) {
        // We do not add content to the summary from base fields, skip them
        // keeps performance while building the paragraph summary.
        continue;
      }

      /** @var \Drupal\Core\Field\FieldItemListInterface $fieldItemList */
      $fieldItemList = $paragraph->get($fieldName);
      $fieldColumn = $this->getFieldColumn($fieldName);
      if (empty($row[$fieldColumn]['value'])) {
        $row[$fieldColumn]['value'] = [];
      }
      if (empty($row[$fieldColumn]['span'])) {
        $row[$fieldColumn]['span'] = $this->getFieldSpan($fieldDefinition);
      }

      if (empty($fieldItemList->getValue())) {
        continue;
      }

      $value = NULL;
      switch ($fieldDefinition->getType()) {
        case 'boolean':
          $value = $this->renderBooleanField($fieldItemList);
          break;

        case 'text_with_summary':
        case 'text':
        case 'text_long':
        case 'string':
        case 'string_long':
          $value = $this->renderStringField($fieldItemList, !in_array($fieldName, [
            'field_as_values_curr_text',
            'field_as_description',
          ]));
          break;

        case 'link':
          $value = $this->renderLinkField($fieldItemList);
          break;

        case 'entity_reference':
        case 'entity_reference_revisions':
          $value = $this->renderEntityReferenceField($fieldItemList);

          foreach ($fieldItemList as $childEntityValue) {
            $cssClass = _iucn_assessment_level_class($childEntityValue->target_id);
            if (!empty($cssClass)) {
              $row[$fieldColumn]['class'] = $cssClass;
            }
          }

          $fieldsWithParents = [
            'field_as_benefits_category',
            'field_as_threats_categories',
          ];
          if (in_array($fieldName, $fieldsWithParents)) {
            // For these fields we insert an extra column for term parents because
            // categories have sub-categories.
            $insertedParents = [];
            for ($i = 0; $i < $fieldItemList->count(); $i++) {
              /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $childEntityValue */
              $childEntityValue = $fieldItemList->get($i);
              /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
              $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
              $parents = $termStorage->loadParents($childEntityValue->target_id);
              if (!empty($parents) && !in_array(key($parents), $insertedParents)) {
                $insertedParents [] = key($parents);
                $childEntityValue->setValue(key($parents));
              }
              else {
                $fieldItemList->removeItem($i);
                $i--;
              }
            }
            $childrenCell = [
              "{$fieldName}_child_category" => [
                'value' => [$value],
                'span' => $this->getFieldSpan($fieldDefinition),
              ],
            ];
            $row = $row + $childrenCell;
            $value = $this->renderEntityReferenceField($fieldItemList);
          }
          break;

        case 'image':
        case 'file':
          // @todo
          break;
      }

      if (empty($value)) {
        continue;
      }

      $fieldGroup = (string) $this->getFieldGroup($fieldName);
      if (empty($fieldGroup)) {
        $row[$fieldColumn]['value'][] = $value;
        continue;
      }

      if (empty($row[$fieldColumn]['value'][$fieldGroup])) {
        $row[$fieldColumn]['value'][$fieldGroup] = sprintf("<div class='group-label'>%s: </div>", $fieldGroup);
      }
      $row[$fieldColumn]['value'][$fieldGroup] .= $value;
    }

    return $row;
  }

  /**
   * Returns the markup for a boolean field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *
   * @return string
   */
  protected function renderBooleanField(FieldItemListInterface $fieldItemList) {
    return !empty($fieldItemList->value)
      ? '<span class="field-boolean-tick">' . html_entity_decode('&#10004;') . '</span>'
      : '';
  }

  /**
   * Returns the markup for a string field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   * @param bool $truncate
   *
   * @return string
   */
  protected function renderStringField(FieldItemListInterface $fieldItemList, $truncate = FALSE) {
    $value = trim($fieldItemList->value);
    if ($truncate === TRUE && strlen($value) > 600) {
      $value = Unicode::truncate($text, 600) . '...';
    }
    return $value;
  }

  /**
   * Returns the markup for a link field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *
   * @return |null
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function renderLinkField(FieldItemListInterface $fieldItemList) {
    if (empty($fieldItemList->first())) {
      return NULL;
    }
    if (!empty($fieldItemList->title)) {
      return $fieldItemList->title;
    }
    // If title is not set, fallback to the uri.
    return $fieldItemList->uri;
  }

  /**
   * Returns the markup for an entity reference field. Also entity_reference_revisions
   * fields should use this method.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *
   * @return mixed|null
   */
  protected function renderEntityReferenceField(FieldItemListInterface $fieldItemList) {
    $viewBuilder = NULL;
    $childrenView = [];
    foreach ($fieldItemList as $childEntityValue) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
      $childEntity = $childEntityValue->entity;
      if (empty($viewBuilder)) {
        $viewBuilder = $this->entityTypeManager->getViewBuilder($childEntity->getEntityTypeId());
      }
      $childView = $viewBuilder->view($childEntity, 'teaser');
      $childrenView[] = render($childView);
    }
    $list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $childrenView,
    ];
    return render($list);
  }

  /**
   * Returns the render array with the paragraph action buttons.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return array
   */
  protected function buildRowActions(ParagraphInterface $paragraph) {
    $fieldName = $this->fieldDefinition->getName();
    $fieldWrapperId = '#edit-' . Html::cleanCssIdentifier($fieldName) . '-wrapper';
    $routeAttributes = [
      'node' => $this->parentNode->id(),
      'node_revision' => $this->parentNode->getRevisionId(),
      'field' => $fieldName,
      'field_wrapper_id' => $fieldWrapperId,
      'paragraph_revision' => $paragraph->getRevisionId(),
      'tab' => $this->request->query->get('tab'),
      'form_display_mode' => $this->getSetting('form_display_mode'),
    ];
    $actions = [
      '#type' => 'container',
      '#weight' => 100,
      'edit' => [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => Url::fromRoute('iucn_assessment.modal_paragraph_edit', $routeAttributes),
        '#access' => $paragraph->access('update'),
      ],
      'delete' => [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => Url::fromRoute('iucn_assessment.modal_paragraph_delete', $routeAttributes),
        '#access' => $paragraph->access('update') && $this->getSetting('only_editable') == FALSE,
      ],
      'compare' => [
        '#type' => 'link',
        '#title' => $this->t('See differences'),
        '#url' => Url::fromRoute('iucn_assessment.paragraph_diff_form', $routeAttributes),
        '#access' => $paragraph->access('update'),
      ],
    ];
    if (!$this->paragraphHasDifferences($paragraph)) {
      $actions['compare']['#access'] = FALSE;
    }

    foreach (Element::children($actions) as $buttonKey) {
      $cssIdentifier = Html::cleanCssIdentifier($buttonKey);
      $actions[$buttonKey]['#attributes'] = [
        'class' => [
          'use-ajax',
          'button',
          'paragraphs-icon-button',
          "paragraphs-icon-button-{$cssIdentifier}",
        ],
        'data-dialog-type' => 'modal',
        'title' => $actions[$buttonKey]['#title'],
      ];
    }
    return $actions;
  }

  /**
   * Returns the column name where the field should be rendered.
   *
   * @param $fieldName
   *
   * @return string
   *  The machine name of the column. Default column is the field name.
   */
  public function getFieldColumn($fieldName) {
    switch ($fieldName) {
      case 'field_as_threats_values_bio':
        return 'field_as_threats_values_wh';

      case 'field_as_threats_extent':
        return 'field_as_threats_in';

      case 'field_as_legality':
      case 'field_as_targeted_species':
      case 'field_invasive_species_names':
        return 'other_information';

      case 'field_as_benefits_hab_trend':
      case 'field_as_benefits_hab_level':
      case 'field_as_benefits_pollut_trend':
      case 'field_as_benefits_pollut_level':
      case 'field_as_benefits_oex_trend':
      case 'field_as_benefits_oex_level':
      case 'field_as_benefits_climate_trend':
      case 'field_as_benefits_climate_level':
      case 'field_as_benefits_invassp_level':
      case 'field_as_benefits_invassp_trend':
        return 'negative_factors';
    }
    return $fieldName;
  }

  /**
   * Some fields which are rendered in the same column are also grouped.
   * (e.g. "Pollution: {field_as_benefits_pollut_trend} trend,
   * {field_as_benefits_pollut_level} level"
   *
   * @param $fieldName
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *  The label of the group.
   */
  public function getFieldGroup($fieldName) {
    switch ($fieldName) {
      case 'field_as_legality':
        return $this->t('Legality');

      case 'field_as_targeted_species':
        return $this->t('Targeted species');

      case 'field_invasive_species_names':
        return $this->t('Invasive/problematic species');

      case 'field_as_benefits_hab_trend':
      case 'field_as_benefits_hab_level':
        return $this->t('Habitat change');

      case 'field_as_benefits_pollut_trend':
      case 'field_as_benefits_pollut_level':
        return $this->t('Pollution');

      case 'field_as_benefits_oex_trend':
      case 'field_as_benefits_oex_level':
        return $this->t('Over exploitation');

      case 'field_as_benefits_climate_trend':
      case 'field_as_benefits_climate_level':
        return $this->t('Climate change');

      case 'field_as_benefits_invassp_trend':
      case 'field_as_benefits_invassp_level':
        return $this->t('Invasive species');
    }
    return NULL;
  }

  /**
   * We are using CSS grid template to display a table and the columns need to
   * have different widths based on the field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|NULL $fieldDefinition
   *
   * @return int
   */
  public function getFieldSpan(FieldDefinitionInterface $fieldDefinition = NULL) {
    if (empty($fieldDefinition)) {
      return 2;
    }
    $fieldName = $fieldDefinition->getName();
    if ($fieldDefinition->getType() == 'boolean'
      || $fieldName == 'field_as_protection_rating'
      || $fieldName == 'field_as_values_criteria') {
      return 1;
    }
    if ($fieldDefinition->getType() == 'string_long') {
      return 3;
    }
    return 2;
  }

  /**
   * @inheritdoc
   */
  public static function getWidgetState(array $parents, $fieldName, FormStateInterface $form_state) {
    // Fix some issues with the diff form save.
    return parent::getWidgetState($parents, $fieldName, $form_state) ?: [];
  }

}
