<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
class RowParagraphsWidget extends ParagraphsWidget {

  /**
   * The last paragraph entity that has been processed.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraphsEntity;

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

    $summary[] = $this->t('Show numbers: @show_numbers', ['@show_numbers' => $show_numbers]);
    if (!empty($empty_message)) {
      $summary[] = $this->t('Empty message: @empty_message', ['@empty_message' => $empty_message]);
    }
    return $summary;
  }

  /**
   * Check if a paragraph has any differences for the rendered fields.
   *
   * @param $paragraph_id
   * @param $rendered_fields
   * @return bool
   */
  public function isParagraphWithDiff($paragraph_id, $rendered_fields) {
    if (!empty($this->diff)) {
      foreach ($this->diff as $vid => $diff) {
        if (in_array($paragraph_id, array_keys($diff))) {
          if ($diff[$paragraph_id]['entity_type'] != 'paragraph') {
            continue;
          }
          foreach (array_keys($diff[$paragraph_id]['diff']) as $diff_field) {
            if (in_array($diff_field, $rendered_fields)) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Build the diff button for the row.
   *
   * @param array $element
   * @param ParagraphInterface $paragraphs_entity
   * @param string $field_wrapper
   * @param string $field_name
   */
  public function buildDiffButton(array &$element, ParagraphInterface $paragraphs_entity, $field_wrapper, $field_name) {
    $element['top']['actions']['actions']['diff_button'] = [
      '#type' => 'submit',
      '#value' => 'See differences',
      '#weight' => 2,
      '#ajax' => [
        'event' => 'click',
        'url' => Url::fromRoute('iucn_assessment.paragraph_diff_form', [
          'node' => $this->parentNode->id(),
          'node_revision' => $this->parentNode->getRevisionId(),
          'field' => $field_name,
          'field_wrapper_id' => "#$field_wrapper",
          'paragraph_revision' => $paragraphs_entity->getRevisionId(),
        ]),
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
      '#access' => $paragraphs_entity->access('update'),
      '#attributes' => [
        'class' => [
          'paragraphs-icon-button',
          'paragraphs-icon-button-compare',
          'use-ajax',
        ],
        'title' => $this->t('See differences'),
      ],
    ];
  }

  /**
   * Build the edit button as an ajax callback.
   *
   * @param array $element
   * @param ParagraphInterface $paragraphs_entity
   * @param $field_wrapper
   * @param $field_name
   * @param $delta
   */
  public function buildAjaxEditButton(array &$element, ParagraphInterface $paragraphs_entity, $field_wrapper, $field_name) {
    $tab = \Drupal::request()->query->get('tab');
    $element['top']['actions']['actions']['edit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit'),
      '#ajax' => [
        'event' => 'click',
        'url' => Url::fromRoute('iucn_assessment.modal_paragraph_edit', [
          'node' => $this->parentNode->id(),
          'node_revision' => $this->parentNode->getRevisionId(),
          'field' => $field_name,
          'field_wrapper_id' => "#$field_wrapper",
          'paragraph_revision' => $paragraphs_entity->getRevisionId(),
          'tab' => $tab,
        ]),
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
      '#attributes' => [
        'class' => ['paragraphs-icon-button', 'paragraphs-icon-button-edit'],
        'title' => $this->t('Edit'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    unset($element['top']['type']);
    unset($element['top']['icons']);
    unset($element['top']['summary']);

    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];

    $widget_state = static::getWidgetState($parents, $field_name, $form_state);

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $paragraphs_entity = $widget_state['paragraphs'][$delta]['entity'];

    $summary_components = $this->getSummaryComponents($paragraphs_entity);
    $summary_containers = $this->getSummaryContainers($summary_components);
    $element['top']['summary'] = $summary_containers;
    $count = $this->calculateColumnCount($summary_components) + 1;
    $element['top']['#attributes']['class'][] = "paragraph-top-col-$count";
    $this->colCount = $count;


    // Check if we need to show the diff for a paragraph.
    // We should show the diff if the paragraph id appears in the diff array
    // and at least one field that is visible in this row was changed.
    $show_diff = FALSE;
    if ($this->parentNode->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW
     && $this->isNewParagraph($this->parentNode, AssessmentWorkflow::STATUS_UNDER_EVALUATION, $field_name, $paragraphs_entity->id())
     && !$this->isNewParagraph($this->parentNode, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, $field_name, $paragraphs_entity->id())) {
      $element['top']['#attributes']['class'][] = "paragraph-new-row";
    }
    else {
      if ($this->isParagraphWithDiff($paragraphs_entity->id(), array_keys($summary_containers))
        && in_array($this->parentNode->field_state->value, [
          AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
          AssessmentWorkflow::STATUS_UNDER_COMPARISON,
        ])) {
        $show_diff = TRUE;
      }
    }

    $element['top']['actions']['#weight'] = 9999;
    $element['top']['actions']['#prefix'] = '<div class="paragraph-summary-component">';
    $element['top']['actions']['#suffix'] = '</div>';

    $field_wrapper = 'edit-' . str_replace('_', '-', $field_name) . '-wrapper';
    if (!empty($element['top']['actions']['actions']['edit_button']) && $show_diff) {
      $this->buildDiffButton($element, $paragraphs_entity, $field_wrapper, $field_name);
      $element['#attributes']['class'][] = 'paragraph-diff-row';
    }

    $this->buildAjaxEditButton($element, $paragraphs_entity, $field_wrapper, $field_name);

    $element['#paragraph_id'] = $paragraphs_entity->id();
    $this->paragraphsEntity = $paragraphs_entity;

    $this->appendAjaxDeleteButton($element, $paragraphs_entity, $field_name, $field_wrapper);

    return $element;
  }

  public function appendAjaxDeleteButton(&$element, ParagraphInterface $paragraphs_entity, $field_name, $field_wrapper) {
    unset($element['top']['actions']['dropdown_actions']);

    $element['top']['actions']['actions']['remove_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#attributes' => [
        'title' => $this->t('Remove'),
        'class' => [
          'paragraphs-icon-delete',
          'paragraphs-icon-button',
        ],
      ],
      '#ajax' => [
        'event' => 'click',
        'url' => Url::fromRoute('iucn_assessment.modal_paragraph_delete', [
          'node' => $this->parentNode->id(),
          'node_revision' => $this->parentNode->getRevisionId(),
          'field' => $field_name,
          'field_wrapper_id' => "#$field_wrapper",
          'paragraph_revision' => $paragraphs_entity->getRevisionId(),
        ]),
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
    ];
  }

  /**
   * Check if a paragraph is new compared to previous revisions of a certain state.
   *
   * @param NodeInterface $node
   * @param $field_name
   * @param $paragraph_id
   * @return bool
   */
  public function isNewParagraph(NodeInterface $node, $state, $field_name, $paragraph_id) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $revision = $assessment_workflow->getRevisionByState($node, $state);
    if (empty($revision)) {
      return FALSE;
    }
    return !in_array($paragraph_id, array_column($revision->get($field_name)->getValue(), 'target_id'))
      && in_array($paragraph_id, array_column($node->get($field_name)->getValue(), 'target_id'));
  }

  /**
   * Calculate the column count for a row.
   *
   * @param array $components
   * @return int
   */
  public function calculateColumnCount(array $components) {
    $count = count($components);

    foreach ($components as $key => $component) {
      if (!empty($component['span']) && $component['span'] == 2) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Build the table header.
   *
   * @param $elements
   */
  public function buildHeader(&$elements) {
    // Use the last rendered paragraph to build the header based on it's fields.
    if (!empty($this->paragraphsEntity)) {
      $header_components = $this->getHeaderComponents($this->paragraphsEntity);
      $header_components += ['actions' => $this->t('Actions')];
      $header_containers = $this->getHeaderContainers($header_components);
      $header_containers['actions']['#prefix'] = '<div class="paragraph-summary-component">';
      $header_containers['actions']['#suffix'] = '</div>';
      $count = $this->colCount;

      $header = [
        'header' => [
          '#type' => 'container',
          '#weight' => -2000,
          '#attributes' => [
            'class' => [
              'paragraph-top',
              'paragraph-header',
              "paragraph-top-col-$count",
            ],
          ],
          'data' => $header_containers,
        ],
      ];
      $elements += $header;
    }
    else {
      // Show an empty message if the table is empty.
      $empty_message = $this->getSetting('empty_message');
      if (!empty($empty_message)) {
        $elements['empty_message'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['paragraph-summary-component', 'paragraph-empty']],
          'data' => ['#markup' => $empty_message],
        ];
      }
    }
  }

  public function buildAddMoreAjaxButton(&$elements, $field_name) {
    $add_more_button = array_keys($elements['add_more'])[0];
    $label = $elements['add_more'][$add_more_button]['#value'];
    $target_paragraph = FieldConfig::loadByName('node', 'site_assessment', $field_name)
      ->getSetting('handler_settings')['target_bundles'];
    $bundle = reset($target_paragraph);
    if (!empty($this->parentNode->id())) {
      $elements['add_more'][$add_more_button] = [
        '#type' => 'submit',
        '#value' => $this->t('Add more'),
        '#ajax' => [
          'event' => 'click',
          'url' => Url::fromRoute('iucn_assessment.modal_paragraph_add', [
            'node' => $this->parentNode->id(),
            'node_revision' => $this->parentNode->getRevisionId(),
            'field' => $field_name,
            'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field_name) . '-wrapper',
            'bundle' => $bundle,
          ]),
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $this->parentNode = $form_state->getFormObject()->getEntity();
    $settings = json_decode($this->parentNode->field_settings->value, TRUE);
    $this->diff = !empty($settings['diff']) ? $settings['diff'] : NULL;

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $field_settings_json = $this->parentNode->field_settings->value;
    $field_settings = json_decode($field_settings_json, TRUE);
    $diff = !empty($field_settings['diff']) ? $field_settings['diff'] : NULL;

    $this->buildHeader($elements);

    // Make the add more button open a modal.
    if (!empty($elements['add_more'])) {
      $field_name = $this->fieldDefinition->getName();
      $this->buildAddMoreAjaxButton($elements, $field_name);
    }

    $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $elements['#attached']['library'][] = 'iucn_backend/font-awesome';
    $elements['#prefix'] = str_replace('paragraphs-tabs-wrapper', 'raw-paragraphs-tabs-wrapper', $elements['#prefix']);

    // Show deleted paragraphs.
    if ($this->parentNode->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
      $this->appendDeletedParagraphs($elements, $field_name);
    }

    if ($this->parentNode->field_state->value == AssessmentWorkflow::STATUS_UNDER_COMPARISON) {
      $this->appendReviewerParagraphs($elements, $field_name);
    }

    return $elements;
  }

  public function getReviewerParagraphs($field_name) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $current_revision = $this->parentNode;
    $reviewer_revisions = $assessment_workflow->getReviewerRevisions($current_revision);
    if (empty($reviewer_revisions)) {
      return NULL;
    }
    $reviewer_added_paragraphs = [];
    foreach ($reviewer_revisions as $reviewer_revision) {
      $current_revision_paragraphs = array_column($current_revision->get($field_name)->getValue(), 'target_id');
      $reviewer_revision_paragraphs = array_column($reviewer_revision->get($field_name)->getValue(), 'target_id');
      $added_paragraphs = array_diff($reviewer_revision_paragraphs, $current_revision_paragraphs);
      $reviewer_added_paragraphs = array_merge($reviewer_added_paragraphs, $added_paragraphs);
    }
    return $reviewer_added_paragraphs;
  }

  public function appendReviewerParagraphs(&$elements, $field_name) {
    $reviewer_paragraphs = $this->getReviewerParagraphs($field_name);
    $reviewer_paragraphs_rows = $this->getParagraphsRows($reviewer_paragraphs, $field_name, 'paragraph-new-row');
    if (!empty($reviewer_paragraphs_rows)) {
      foreach ($reviewer_paragraphs_rows as $paragraph_id => &$reviewer_paragraph_row) {
        $this->appendRevertParagraphAction($reviewer_paragraph_row, $paragraph_id, $field_name, 'accept');
      }
      $elements += $reviewer_paragraphs_rows;
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
      '#type' => 'submit',
      '#value' => $title,
      '#ajax' => [
        'event' => 'click',
        'url' => Url::fromRoute('iucn_assessment.revert_paragraph', [
          'node' => $this->parentNode->id(),
          'node_revision' => $this->parentNode->getRevisionId(),
          'field' => $field_name,
          'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field_name) . '-wrapper',
          'paragraph' => $paragraph_id,
        ]),
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
      '#attributes' => [
        'class' => [
          'paragraphs-icon-button',
          $icon,
        ],
        'title' => $title,
      ],
    ];
    if (!empty($author)) {
      $tooltip = $this->t('Added by: @author', ['@author' => $author]);
      $paragraph_row['actions']['actions']['revert']['#prefix'] = '<div class="paragraph-author">' . $tooltip . '</div>';
    }

  }

  public function getAssessorDeletedParagraphs($field_name) {
    /** @var AssessmentWorkflow $assessment_workflow */
    $assessment_workflow = \Drupal::service('iucn_assessment.workflow');
    $current_revision = $this->parentNode;
    $under_evaluation_revision = $assessment_workflow->getRevisionByState($current_revision, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    if (empty($under_evaluation_revision)) {
      return NULL;
    }
    $assessor_deleted_paragraphs = $this->getDeletedParagraphs($current_revision, $under_evaluation_revision, $field_name);
    $under_as_revision = $assessment_workflow->getRevisionByState($current_revision, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT);
    if (empty($under_as_revision)) {
      return $assessor_deleted_paragraphs;
    }

    $coordinator_deleted_paragraphs = $this->getDeletedParagraphs($current_revision, $under_as_revision, $field_name);
    return array_diff($assessor_deleted_paragraphs, $coordinator_deleted_paragraphs);
  }

  public function getDeletedParagraphs(NodeInterface $new_revision, NodeInterface $old_revision, $field_name) {
    $new_revision_paragraphs = array_column($new_revision->get($field_name)->getValue(), 'target_id');
    $old_revision_paragraphs = array_column($old_revision->get($field_name)->getValue(), 'target_id');
    $deleted_paragraphs = array_diff($old_revision_paragraphs, $new_revision_paragraphs);
    return $deleted_paragraphs;
  }

  public function getParagraphsRows($paragraphs, $field_name, $row_class = '') {
    $elements = [];
    if (!empty($paragraphs)) {
      foreach ($paragraphs as $paragraph) {
        $components = $this->getSummaryComponents(Paragraph::load($paragraph));
        $containers = $this->getSummaryContainers($components);
        $column_count = $this->calculateColumnCount($components) + 1;
        $elements[$paragraph] = [
          '#type' => 'container',
          'top' => ['summmary' => $containers],
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
              "paragraph-top-col-$column_count",
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
   * @param $field_name
   */
  public function appendDeletedParagraphs(&$elements, $field_name) {
    $deleted_paragraphs = $this->getAssessorDeletedParagraphs($field_name);
    $deleted_paragraphs_rows = $this->getParagraphsRows($deleted_paragraphs, $field_name, 'paragraph-deleted-row');
    if (!empty($deleted_paragraphs_rows)) {
      foreach ($deleted_paragraphs_rows as $paragraph_id => &$deleted_paragraph_row) {
        $this->appendRevertParagraphAction($deleted_paragraph_row, $paragraph_id, $field_name, 'revert');
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
  public function getHeaderComponents(ParagraphInterface $paragraph) {
    $header = [];
    $grouped_fields = self::getGroupedFields();
    if ($this->getSetting('show_numbers') == 'yes') {
      $header['num'] = [
        'value' => $this->t('No.'),
        'span' => 1,
      ];
    }

    $components = self::getFieldComponents($paragraph, $this->getSetting('form_display_mode'));
    foreach (array_keys($components) as $field_name) {
      if (!$paragraph->hasField($field_name)) {
        continue;
      }
      $field_definition = $paragraph->getFieldDefinition($field_name);
      if (!empty($grouped_fields[$field_name])) {
        $label = $grouped_fields[$field_name]['label'];
        $field_name = $grouped_fields[$field_name]['grouped_with'];
      }
      else {
        $label = $field_definition->getLabel();
      }
      $header[$field_name]['value'] = $label;
      if ($field_definition->getType() == 'string_long') {
        $header[$field_name]['span'] = 2;
      }
      else {
        $header[$field_name]['span'] = 1;
      }
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
   * Creates the markup for header components.
   *
   * @param array $components
   *   The header components.
   *
   * @return array
   *   The header markup.
   */
  public function getHeaderContainers(array $components) {
    $containers = $this->getSummaryContainers($components);
    foreach ($containers as &$container) {
      $container['#attributes']['title'] = $container['data'];
    }
    return $containers;
  }

  /**
   * Creates the markup for the summary components.
   *
   * @param array $components
   *   The summary components.
   *
   * @return array
   *   The summary markup.
   */
  public function getSummaryContainers(array $components) {
    $containers = [];
    foreach ($components as $key => $component) {
      $span = !empty($component['span']) ? $component['span'] : 1;
      if (is_array($component['value'])) {
        foreach ($component['value'] as $idx => $value) {
          if (empty($value)) {
            unset($component['value'][$idx]);
          }
        }
        $data = !empty($component['value']) ? implode('; ', $component['value']) : '';
      }
      else {
        $data = $component['value'];
      }
      $containers[$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'paragraph-summary-component',
            "paragraph-summary-component-$key",
            "paragraph-summary-component-span-$span",
          ],
        ],
        'data' => ['#markup' => $data],
      ];
      if ($key === 'actions') {
        $containers[$key]['#attributes']['class'] = 'paragraphs-actions';
      }
    }

    return $containers;
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
  public static function getFieldComponents(ParagraphInterface $paragraph, $form_display_mode = NULL) {
    $bundle = $paragraph->getType();
    if (empty($form_display_mode)) {
      $form_display_mode = 'default';
    }
    $components = EntityFormDisplay::load("paragraph.$bundle.$form_display_mode")
      ->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    return $components;
  }

  /**
   * Returns an array containing a summary for every field inside a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return array
   *   The summary array.
   */
  public function getSummaryComponents(ParagraphInterface $paragraph) {
    $summary = [];
    $grouped_fields = self::getGroupedFields();
    static $num = 0;
    if ($this->getSetting('show_numbers') == 'yes') {
      $num += 1;
      $summary['num']['value'] = $num;
    }

    $components = self::getFieldComponents($paragraph, $this->getSetting('form_display_mode'));
    foreach (array_keys($components) as $field_name) {
      // Components can be extra fields, check if the field really exists.
      if (!$paragraph->hasField($field_name)) {
        continue;
      }
      $field_definition = $paragraph->getFieldDefinition($field_name);
      // We do not add content to the summary from base fields, skip them
      // keeps performance while building the paragraph summary.
      if (!($field_definition instanceof FieldConfigInterface) || !$paragraph->get($field_name)
          ->access('view')) {
        continue;
      }


      if (!empty($grouped_fields[$field_name])) {
        $summary_field_name = $grouped_fields[$field_name]['grouped_with'];
      }
      else {
        $summary_field_name = $field_name;
      }


      $text_summary = $this->getTextSummary($paragraph, $field_name, $field_definition);
      $value = $text_summary;

      if ($field_definition->getType() == 'image' || $field_definition->getType() == 'file') {
        $value = $paragraph->getFileSummary($field_name);
      }

      if ($field_definition->getType() == 'entity_reference_revisions') {
        $value = $this->getNestedSummary($paragraph, $field_name);
      }

      if ($field_definition->getType() == 'boolean') {
        $config = FieldConfig::loadByName('paragraph', $paragraph->bundle(), $field_name);
        $value = !empty($paragraph->{$field_name}->value)
          ? $config->getSetting('on_label')
          : $config->getSetting('off_label');
      }

      if ($field_type = $field_definition->getType() == 'entity_reference') {
        if ($paragraph->get($field_name)->entity && $paragraph->get($field_name)->entity->access('view label')) {
          $entities = $paragraph->get($field_name)->getValue();
          $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
          $ids = array_column($entities, 'target_id');
          $labels = [];
          foreach ($ids as $id) {
            $entity = \Drupal::entityTypeManager()->getStorage($target_type)->load($id);
            if ($target_type == 'taxonomy_term') {
              if (!$this->isHiddenTerm($entity)) {
                $label = $this->getEntityLabel($entity);
              }
            }
            else {
              $label = $entity->label();
            }
            $labels[] = $label;
          }
          $value = !empty($labels) ? implode(', ', $labels) : NULL;
        }
      }

      // Add the Block admin label referenced by block_field.
      if ($field_definition->getType() == 'block_field') {
        if (!empty($paragraph->get($field_name)->first())) {
          $block_admin_label = $paragraph->get($field_name)
            ->first()
            ->getBlock()
            ->getPluginDefinition()['admin_label'];
          $value = $block_admin_label;
        }
      }

      if ($field_definition->getType() == 'link') {
        if (!empty($paragraph->get($field_name)->first())) {
          // If title is not set, fallback to the uri.
          if ($title = $paragraph->get($field_name)->title) {
            $value = $title;
          }
          else {
            $value = $paragraph->get($field_name)->uri;
          }
        }
      }

      if (!array_key_exists($summary_field_name, $summary)) {
        $summary[$summary_field_name]['value'] = [];
      }

      $prefix = self::getSummaryPrefix($field_name);
      if (!empty($prefix) && !empty($value)) {
        $value = $this->t("$prefix - @value", ['@value' => $value]);
      }

      $summary[$summary_field_name]['value'][] = $value;
      if ($field_definition->getType() == 'string_long') {
        $summary[$summary_field_name]['span'] = 2;
      }
      else {
        $summary[$summary_field_name]['span'] = 1;
      }
    }

    return $summary;
  }

  /**
   * Returns the summary of a paragraph's text field.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition for the field.
   *
   * @return string
   *   The summary.
   */
  public function getTextSummary(ParagraphInterface $paragraph, $field_name, FieldDefinitionInterface $field_definition) {
    $text_types = [
      'text_with_summary',
      'text',
      'text_long',
      'list_string',
      'string',
      'string_long',
    ];

    $excluded_text_types = [
      'parent_id',
      'parent_type',
      'parent_field_name',
    ];

    $summary = '';
    if (in_array($field_definition->getType(), $text_types)) {
      if (in_array($field_name, $excluded_text_types)) {
        return $summary;
      }

      $text = $paragraph->get($field_name)->value;
      if (strlen($text) > 600) {
        $text = Unicode::truncate($text, 600);
        $text .= '...';
      }
      $summary = $text;
    }

    return trim($summary);
  }

  /**
   * Returns the text summary of a referenced paragraph field.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The text summary.
   */
  protected function getNestedSummary(ParagraphInterface $paragraph, $field_name) {
    $summary = [];

    foreach ($paragraph->get($field_name) as $item) {
      $entity = $item->entity;
      if ($entity instanceof ParagraphInterface) {
        $summary_components = $this->getSummaryComponents($entity);
        $first_component = reset($summary_components);
        $summary[] = is_array($first_component['value'])
          ? implode(', ', $first_component['value'])
          : $first_component['value'];
      }
    }

    if (empty($summary)) {
      return '';
    }

    $paragraph_summary = implode(', ', $summary);
    return $paragraph_summary;
  }

  /**
   * Get the array defining grouped fields.
   *
   * The key of the array is the field that needs to be grouped with
   * another field, under a common label.
   *
   * @return array
   *   The grouped fields.
   */
  public static function getGroupedFields() {
    return [
      'field_as_benefits_hab_trend' => [
        'grouped_with' => 'field_as_benefits_hab_level',
        'label' => t('Habitat'),
      ],
      'field_as_benefits_pollut_trend' => [
        'grouped_with' => 'field_as_benefits_pollut_level',
        'label' => t('Pollution'),
      ],
      'field_as_benefits_oex_trend' => [
        'grouped_with' => 'field_as_benefits_oex_level',
        'label' => t('Overexploatation'),
      ],
      'field_as_benefits_climate_trend' => [
        'grouped_with' => 'field_as_benefits_climate_level',
        'label' => t('Climate change'),
      ],
      'field_as_benefits_invassp_trend' => [
        'grouped_with' => 'field_as_benefits_invassp_level',
        'label' => t('Invasive species'),
      ],

    ];
  }

  /**
   * Retrieves a prefix that should show up before a paragraph summary value.
   *
   * @param $field
   *   The name of the field.
   *
   * @return mixed|null
   *   The prefix.
   */
  public static function getSummaryPrefix($field) {
    $prefixes = [
      'field_as_benefits_hab_trend' => t('Trend'),
      'field_as_benefits_pollut_trend' => t('Trend'),
      'field_as_benefits_oex_trend' => t('Trend'),
      'field_as_benefits_climate_trend' => t('Trend'),
      'field_as_benefits_invassp_trend' => t('Trend'),
      'field_as_benefits_hab_level' => t('Impact level'),
      'field_as_benefits_pollut_level' => t('Impact level'),
      'field_as_benefits_oex_level' => t('Impact level'),
      'field_as_benefits_climate_level' => t('Impact level'),
      'field_as_benefits_invassp_level' => t('Impact level'),
    ];
    return !empty($prefixes[$field]) ? $prefixes[$field] : NULL;
  }

  /**
   * Retrieves the label for an entity.
   *
   * @param \Drupal\Core\Entity\Entity $entity
   *   The entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|mixed|null|string
   *   The label
   */
  protected function getEntityLabel(Entity $entity) {
    $moduleHandler = \Drupal::service('module_handler');
    if (!$moduleHandler->moduleExists('iucn_fields')) {
      return NULL;
    }
    $label = '';
    if ($entity->getEntityType()->id() == 'taxonomy_term') {
      /** @var \Drupal\Core\Entity\Term $entity */
      $tid = $entity->id();
      /** @var \Drupal\iucn_fields\Plugin\TermAlterService $term_alter_service */
      $term_alter_service = \Drupal::service('iucn_fields.term_alter');
      $term_new_name = $term_alter_service->getTermLabelForYear($tid, $this->parentNode->field_as_cycle->value);
      if (!empty($term_new_name)) {
        $label = $term_new_name;
      }
    }
    if (empty($label)) {
      $label = $entity->label();
    }
    return $label;
  }

  protected function isHiddenTerm(Entity $entity) {
    $moduleHandler = \Drupal::service('module_handler');
    if (!$moduleHandler->moduleExists('iucn_fields')) {
      return FALSE;
    }
    if ($entity->getEntityType()->id() != 'taxonomy_term') {
      return FALSE;
    }
    /** @var \Drupal\Core\Entity\Term $entity */
    $tid = $entity->id();
    /** @var \Drupal\iucn_fields\Plugin\TermAlterService $term_alter_service */
    $term_alter_service = \Drupal::service('iucn_fields.term_alter');
    return $term_alter_service->isTermHiddenForYear($tid, $this->parentNode->field_as_cycle->value);
  }

}
