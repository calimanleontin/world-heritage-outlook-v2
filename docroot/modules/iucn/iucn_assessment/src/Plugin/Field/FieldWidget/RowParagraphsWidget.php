<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Drupal\iucn_assessment\Form\AssessmentEntityFormTrait;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
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

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\paragraphs\ParagraphInterface */
  protected $lastProcessedParagraph;

  /** @var int */
  protected $numberOfColumns;

  /** @var \Drupal\node\NodeInterface */
  protected $parentNode;

  /** @var string */
  protected $parentFieldName;

  /** @var \Drupal\node\NodeInterface */
  protected $parentNodeInitialRevision;

  /** @var array */
  protected $diff;

  /** @var bool */
  protected $showDifferences = FALSE;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, RequestStack $requestStack, RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager, AssessmentWorkflow $assessmentWorkflow) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->request = $requestStack->getCurrentRequest();
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $this->workflowService = $assessmentWorkflow;
    $this->parentFieldName = $this->fieldDefinition->getName();
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
      $container->get('entity_type.manager'),
      $container->get('iucn_assessment.workflow')
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
      'add_more_text' => t('Add more'),
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
    $elements['add_more_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add more text'),
      '#description' => $this->t('The title of the add more button'),
      '#default_value' => $this->getSetting('add_more_text'),
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
   * @inheritdoc
   */
  protected function getDefaultParagraphTypeMachineName() {
    $default_type = $this->getSetting('default_paragraph_type');
    $allowed_types = $this->getAllowedTypes();
    if ($default_type && isset($allowed_types[$default_type])) {
      return $default_type;
    }
    return !empty($allowed_types) ? key($allowed_types) : NULL;
  }

  /**
   * Check if a paragraph has any differences for the rendered fields.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   * @param array|NULL $fieldsToCheck
   *
   * @return bool
   */
  protected function paragraphHasDifferences(ParagraphInterface $paragraph, array $fieldsToCheck = NULL) {
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

  /**
   * Checks if the paragraph didn't exist on previous revision, but exists on
   * the current one.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return bool
   */
  protected function paragraphIsNew(ParagraphInterface $paragraph) {
    return in_array($paragraph->id(), $this->getNewParagraphsIds());
  }

  /**
   * Returns a list of paragraphs ids which did not exist on previous revision.
   *
   * @param \Drupal\node\NodeInterface|NULL $currentRevision
   *
   * @return array
   */
  protected function getNewParagraphsIds(NodeInterface $currentRevision = NULL) {
    if (empty($currentRevision)) {
      $currentRevision = $this->parentNode;
    }
    $previousValue = $this->getParentFieldValue($this->parentNodeInitialRevision);
    $currentValue = $this->getParentFieldValue($currentRevision);
    return array_diff($currentValue, $previousValue);
  }

  /**
   * Returns a list with both target_id and target_revision_id for each new
   * paragraph.
   *
   * @param \Drupal\node\NodeInterface|NULL $currentRevision
   *
   * @return array
   */
  protected function getNewParagraphsList(NodeInterface $currentRevision = NULL) {
    $newIds = $this->getNewParagraphsIds($currentRevision);
    if (empty($newIds)) {
      return [];
    }
    $new = [];
    $currentValue = $this->getParentFieldValue($currentRevision, NULL);
    foreach ($newIds as $id) {
      foreach ($currentValue as $value) {
        if ($value['target_id'] == $id) {
          $new[] = $value;
        }
      }
    }
    return $new;
  }

  /**
   * Checks if the paragraph has been created on another revision (for example
   * a reviewer revision).
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return bool
   */
  protected function paragraphIsCreatedOnOtherRevision(ParagraphInterface $paragraph) {
    return $this->getParagraphParentNodeRevision($paragraph)->getRevisionId() === $this->parentNode->getRevisionId();
  }

  /**
   * Gets the node revision which holds the paragraph entity. The paragraph could
   * have been created on a reviewer/assessor revision.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return \Drupal\node\NodeInterface|null
   */
  protected function getParagraphParentNodeRevision(ParagraphInterface $paragraph) {
    if ($this->paragraphIsNew($paragraph)) {
      // The paragraph has been already accepted into the actual revision.
      return $this->parentNode;
    }
    foreach ($this->diff as $vid => $diff) {
      $comparedRevision = $this->workflowService->getAssessmentRevision($vid);
      if (in_array($paragraph->id(), $this->getNewParagraphsIds($comparedRevision))) {
        return $comparedRevision;
      }
    }
    return $this->parentNode;
  }

  /**
   * Checks if the paragraph existed on previous revision, but doesn't exist
   * anymore on the current one.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return bool
   */
  protected function paragraphIsDeleted(ParagraphInterface $paragraph) {
    return in_array($paragraph->id(), $this->getDeletedParagraphsIds());
  }

  /**
   * Returns a list of paragraphs ids which were deleted.
   *
   * @return array
   */
  protected function getDeletedParagraphsIds() {
    $previousValue = $this->getParentFieldValue($this->parentNodeInitialRevision);
    $currentValue = $this->getParentFieldValue($this->parentNode);
    return array_diff($previousValue, $currentValue);
  }

  /**
   * Returns a list with both target_id and target_revision_id for each deleted
   * paragraph.
   *
   * @return array
   */
  protected function getDeletedParagraphsList() {
    $deletedIds = $this->getDeletedParagraphsIds();
    if (empty($deletedIds)) {
      return [];
    }
    $deleted = [];
    $previousValue = $this->getParentFieldValue($this->parentNodeInitialRevision, NULL);
    foreach ($deletedIds as $id) {
      foreach ($previousValue as $value) {
        if ($value['target_id'] == $id) {
          $deleted[] = $value;
        }
      }
    }
    return $deleted;
  }

  /**
   * Retrieves a entity reference revisions field value.
   *
   * @param \Drupal\node\NodeInterface|NULL $node
   * @param string $column
   *
   * @return array|mixed
   */
  protected function getParentFieldValue(NodeInterface $node = NULL, $column = 'target_id') {
    if (empty($node)) {
      return [];
    }
    $value = $node->get($this->parentFieldName)->getValue();
    return !empty($column) ? array_column($value, $column) : $value;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    if (!empty($element['subform'])) {
      unset($element['subform']);
    }
    $widgetState = static::getWidgetState($element['#field_parents'], $this->parentFieldName, $form_state);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $this->lastProcessedParagraph = $paragraph = $widgetState['paragraphs'][$delta]['entity'];
    $element['#paragraph_id'] = $paragraph->id();

    $hasDifferences = $this->paragraphHasDifferences($paragraph);
    $isNew = $this->paragraphIsNew($paragraph);
    $paragraphParentNodeRevision = $this->getParagraphParentNodeRevision($paragraph);
    $isCreatedOnOtherRevision = $paragraphParentNodeRevision->getRevisionId() !== $this->parentNode->getRevisionId();
    $isDeleted = $this->paragraphIsDeleted($paragraph);

    $element['top'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['paragraph-top']],
    ];
    try {
      $element['top']['summary'] = $this->buildRow($paragraph);
      $element['top']['actions'] = $this->buildRowActions($paragraph, $hasDifferences, $paragraphParentNodeRevision, $isDeleted);
    }
    catch (\Exception $e) {
      $element['top']['summary'] = ['error' => ['value' => [$this->t('There has been an error while generating this row.')]]];
    }
    if ($this->showDifferences === TRUE) {
      if ($hasDifferences) {
        $element['top']['#attributes']['class'][] = 'paragraph-diff-row';
      }
      if ($isNew || $isCreatedOnOtherRevision) {
        $element['top']['#attributes']['class'][] = 'paragraph-new-row';
      }
      elseif ($isDeleted) {
        $element['top']['#attributes']['class'][] = 'paragraph-deleted-row';
      }
    }
    return $element;
  }

  /**
   * @inheritdoc
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // All actions (add, edit, delete) are handles in an ajax request where
    // also the parent node is saved.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate($element, FormStateInterface $form_state, $form) {
    // Do nothing here since all validation is done on the add/edit form.
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    $this->parentNode = $formObject->getEntity();
    $this->showDifferences = in_array($this->parentNode->field_state->value, [
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_COMPARISON,
      AssessmentWorkflow::STATUS_FINAL_CHANGES,
    ]);
    $settings = json_decode($this->parentNode->field_settings->value, TRUE);
    $this->diff = !empty($settings['diff']) ? $settings['diff'] : [];
    $nodeDiff = array_column($this->diff, 'node');
    $nodeDiff = reset($nodeDiff);

    if (empty($this->parentNode->id())) {
      $elements = parent::formMultipleElements($items, $form, $form_state);
      unset($elements['add_more']);
      $elements[0] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('This field can be completed after the node is created.'),
      ];
      return $elements;
    }

    if (!empty($nodeDiff[$this->parentNode->id()]['initial_revision_id'])) {
      $this->parentNodeInitialRevision = $this->workflowService->getAssessmentRevision($nodeDiff[$this->parentNode->id()]['initial_revision_id']);
    }
    else {
      $previousRevision = $this->workflowService->getPreviousWorkflowRevision($this->parentNode);
      if (!empty($previousRevision)) {
        $initialRevision = $this->workflowService->getPreviousWorkflowRevision($previousRevision);
        $this->parentNodeInitialRevision = !empty($initialRevision)
          ? $initialRevision
          : $previousRevision;
      }
    }

    if ($this->showDifferences) {
      $items = clone $items;

      // Add deleted paragraphs.
      $extraParagraphs = $this->getDeletedParagraphsList();

      foreach ($this->diff as $vid => $diff) {
        // Add new paragraphs created by reviewers.
        $diffParentRevision = $this->workflowService->getAssessmentRevision($vid);
        $newParagraphs = $this->getNewParagraphsList($diffParentRevision);
        $extraParagraphs = array_merge($extraParagraphs, $newParagraphs);
      }

      $currentValue = array_column($this->parentNode->{$this->parentFieldName}->getValue(), 'target_id');
      $formStateStorage = $form_state->getStorage();
      foreach ($extraParagraphs as $item) {
        if (in_array($item['target_id'], $currentValue)) {
          // Paragraph has been accepted or reverted.
          continue;
        }
        $items->appendItem($item);
        $formStateStorage['field_storage']['#parents']['#fields'][$this->parentFieldName]['items_count']++;
      }
      $form_state->setStorage($formStateStorage);
    }

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $elements[] = $this->buildHeaderRow();

    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      if (empty($element['top'])) {
        continue;
      }
      $element['top']['#attributes']['class'][] = "paragraph-top-col-{$this->numberOfColumns}";
      foreach (Element::children($element['top']) as $topKey) {
        if ($topKey == 'actions') {
          continue;
        }
        $element['top'][$topKey] = $this->buildCellsContainers($element['top'][$topKey]);
      }
    }

    if (!empty($elements['add_more'])) {
      $addMoreUrl = Url::fromRoute('iucn_assessment.modal_paragraph_add', [
        'node' => $this->parentNode->id(),
        'node_revision' => $this->parentNode->getRevisionId(),
        'field' => $this->parentFieldName,
        'field_wrapper_id' => get_wrapper_html_id($this->parentFieldName),
        'bundle' => $this->getDefaultParagraphTypeMachineName(),
        'tab' => $this->request->query->get('tab'),
      ]);
      $addMoreButtons = $elements['add_more'];
      foreach (Element::children($addMoreButtons) as $key) {
        $elements['add_more'][$key] = [
          '#type' => 'link',
          '#title' => $this->getSetting('add_more_text'),
          '#url' => $addMoreUrl,
          '#access' => empty($this->getSetting('only_editable')) && $addMoreUrl->access(),
          '#attributes' => [
            'class' => [
              'button',
              'use-ajax',
              'paragraphs-add-more-button',
            ],
            'data-dialog-type' => 'modal',
            'title' => $this->getSetting('add_more_text'),
          ],
        ];
      }
    }

    $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $elements['#attached']['library'][] = 'iucn_backend/font-awesome';
    $elements['#prefix'] = str_replace('paragraphs-tabs-wrapper', 'raw-paragraphs-tabs-wrapper', $elements['#prefix']);
    return $elements;
  }

  /**
   * Includes each cell content in a container render element which has all
   * need attributes for the CSS grid table display.
   *
   * @param $elements
   *
   * @return array
   */
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
   * Returns the render array for the top row.
   *
   * @return array
   */
  public function buildHeaderRow() {
    // Use the last rendered paragraph to build the header based on it's fields.
    if (!empty($this->lastProcessedParagraph)) {
      $row = $this->getHeaderRow($this->lastProcessedParagraph);
      $row += ['actions' => $this->t('Actions')];
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

  /**
   * Returns an array containing the components for the header.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *
   * @return array
   */
  public function getHeaderRow(ParagraphInterface $paragraph) {
    $header = [];
    if ($this->getSetting('show_numbers') == 'yes') {
      $header['num'] = [
        'value' => $this->t('No.'),
        'span' => 1,
      ];
    }

    try {
      $columns = $this->buildRow($paragraph);
      $this->numberOfColumns = array_sum(array_column($columns, 'span')) + 1;
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
            $label = $this->t('Category');
            break;

          case 'field_as_threats_values_wh':
          case 'field_as_threats_values_bio':
            $label = $this->t('Values affected');
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
    }
    catch (\Exception $e) {
      $header = ['error' => ['value' => [$this->t('There has been an error while generating this row.')]]];
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
      $fieldItemList = clone $paragraph->get($fieldName);
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

        case 'list_text':
        case 'list_string':
          $value = $this->renderListField($fieldItemList);
          break;

        case 'string_long':
          $value = $this->renderStringField($fieldItemList, !in_array($fieldName, [
            'field_as_values_curr_text',
            'field_as_description',
          ]));
          $value = nl2br($value);
          break;
        case 'text_with_summary':
        case 'text_long':
        case 'string':
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
            $insertedParents = [];
            $childrenItemList = new FieldItemList($fieldItemList->getDataDefinition());
            for ($i = 0; $i < $fieldItemList->count(); $i++) {
              /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $childEntityValue */
              $childEntityValue = $fieldItemList->get($i);
              /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
              $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
              $parents = $termStorage->loadParents($childEntityValue->target_id);

              if (!empty($parents)) {
                $childrenItemList->appendItem($childEntityValue->getValue());
                if (!in_array(key($parents), $insertedParents)) {
                  $insertedParents [] = key($parents);
                  $childEntityValue->setValue(key($parents));
                }
                else {
                  $fieldItemList->removeItem($i);
                  $i--;
                }
              }
              else {
                $insertedParents[] = $childEntityValue->target_id;
              }
            }
            $childrenRender = $this->renderEntityReferenceField($childrenItemList);
            $childrenCell = [
              "{$fieldName}_child_category" => [
                'value' => [$childrenRender],
                'span' => $this->getFieldSpan($fieldDefinition),
              ],
            ];
            $row = $row + $childrenCell;
            if ($fieldName == 'field_as_threats_categories') {
              $value = !$childrenItemList->isEmpty() ? $this->renderEntityReferenceField($fieldItemList) . '<div class="subcategories">' . $childrenRender . '</div>' : $this->renderEntityReferenceField($fieldItemList);
            }
            else {
              $value = $this->renderEntityReferenceField($fieldItemList);
            }
          }
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
   * Returns the markup for a list field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *
   * @return string
   */
  protected function renderListField(FieldItemListInterface $fieldItemList) {
    $allowedValues = $fieldItemList->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values');
    $values = [];
    foreach ($fieldItemList as $item) {
      $values[] = $allowedValues[$item->value];
    }
    return implode(', ', $values);
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
      $value = Unicode::truncate($value, 600) . '...';
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
   * Returns the markup for an entity reference field. Also
   * entity_reference_revisions fields should use this method.
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
      if (empty($childEntity)) {
        continue;
      }
      if (empty($viewBuilder)) {
        $viewBuilder = $this->entityTypeManager->getViewBuilder($childEntity->getEntityTypeId());
      }
      $childView = $viewBuilder->view($childEntity, 'teaser');
      $childView['#attributes']['class'][] = 'iucn-entity-reference-field-wrapper';
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
   * @param $hasDifferences
   * @param \Drupal\node\NodeInterface $paragraphParentNodeRevision
   * @param $isDeleted
   *
   * @return array
   */
  protected function buildRowActions(ParagraphInterface $paragraph, $hasDifferences, NodeInterface $paragraphParentNodeRevision, $isDeleted) {
    if (empty($this->parentNode) || empty($paragraph->id())) {
      return [];
    }
    $isCreatedOnOtherRevision = $paragraphParentNodeRevision->getRevisionId() !== $this->parentNode->getRevisionId();
    $fieldWrapperId = get_wrapper_html_id($this->parentFieldName);
    $routeAttributes = [
      'node' => $this->parentNode->id(),
      'node_revision' => $this->parentNode->getRevisionId(),
      'field' => $this->parentFieldName,
      'field_wrapper_id' => $fieldWrapperId,
      'paragraph' => $paragraph->id(),
      'paragraph_revision' => $paragraph->getRevisionId(),
      'tab' => $this->request->query->get('tab'),
      'form_display_mode' => $this->getSetting('form_display_mode'),
      'language' => $this->routeMatch->getRouteName() != 'entity.node.content_translation_add'
        ? $paragraphParentNodeRevision->language()->getId()
        : $this->routeMatch->getParameter('target')->getId(),
    ];
    $actions = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => [
        'class' => [
          'paragraph-summary-component',
          "paragraph-summary-component-actions",
          "paragraph-summary-component-span-1",
        ],
      ],
    ];
    $paragraphAuthor = $paragraphParentNodeRevision->getRevisionUser();
    $authorName = $paragraphAuthor instanceof AccountInterface
      ? $paragraphAuthor->getDisplayName()
      : '';
    $buttons = [
      '#type' => 'container',
      '#access' => $paragraph->access('update'),
      'edit' => [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => Url::fromRoute('iucn_assessment.modal_paragraph_edit', $routeAttributes),
        '#access' => !$isDeleted && !$isCreatedOnOtherRevision,
      ],
      'delete' => [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => Url::fromRoute('iucn_assessment.modal_paragraph_delete', $routeAttributes),
        '#access' => $this->getSetting('only_editable') == FALSE && !$isDeleted && !$isCreatedOnOtherRevision,
      ],
      'compare' => [
        '#type' => 'link',
        '#title' => $this->t('See differences'),
        '#url' => Url::fromRoute('iucn_assessment.paragraph_diff_form', $routeAttributes),
        '#access' => $hasDifferences && !$isDeleted && !$isCreatedOnOtherRevision,
      ],
      'revert' => [
        '#type' => 'link',
        '#title' => $this->t('Revert'),
        '#url' => Url::fromRoute('iucn_assessment.revert_paragraph', $routeAttributes),
        '#access' => $isDeleted,
      ],
      'accept' => [
        '#type' => 'link',
        '#title' => $this->t('Accept new row'),
        '#prefix' => sprintf('<div class="paragraph-author">%s</div>', $this->t('Row added by %author', ['%author' => $authorName])),
        '#url' => Url::fromRoute('iucn_assessment.accept_paragraph', $routeAttributes),
        '#access' => $isCreatedOnOtherRevision,
      ],
    ];

    foreach (Element::children($buttons) as $buttonKey) {
      if (!array_key_exists('#access', $buttons[$buttonKey]) || $buttons[$buttonKey]['#access'] == TRUE) {
        /** @var \Drupal\Core\Url $url */
        $url = $buttons[$buttonKey]['#url'];
        $buttons[$buttonKey]['#access'] = $url->access();
      }
      $cssIdentifier = Html::cleanCssIdentifier($buttonKey);
      $buttons[$buttonKey]['#attributes'] = [
        'class' => [
          'use-ajax',
          'button',
          'paragraphs-icon-button',
          "paragraphs-icon-button-{$cssIdentifier}",
        ],
        'data-dialog-type' => 'modal',
        'title' => $buttons[$buttonKey]['#title'],
      ];
    }
    $actions['buttons'] = $buttons;
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
      case 'field_as_threats_categories':
        return 'field_as_threats_categories_child_category';

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
