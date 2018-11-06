<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Utility\Unicode;

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
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $this->parentNode = $form_state->getFormObject()->getEntity();

    unset($element['top']['type']);
    unset($element['top']['icons']);
    unset($element['top']['summary']);

    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $paragraphs_entity = NULL;
    $widget_state = static::getWidgetState($parents, $field_name, $form_state);

    $paragraphs_entity = $widget_state['paragraphs'][$delta]['entity'];
    $components = $this->getSummaryComponents($paragraphs_entity);
    $containers = $this->getSummaryContainers($components);

    $element['top']['actions']['#weight'] = 9999;
    $element['top']['actions']['#prefix'] = '<div class="paragraph-summary-component">';
    $element['top']['actions']['#suffix'] = '</div>';

    if (!empty($element['top']['actions']['actions']['edit_button'])) {
      // Create the custom 'Diff' button
      // @todo
      //      $element['top']['actions']['actions']['diff_button'] = [
      //        '#type' => 'submit',
      //        '#value' => $this->t('Diff'),
      //        '#name' => substr($element['top']['actions']['actions']['edit_button']['#name'], 0 , -4) . 'diff',
      //        '#weight' => 2,
      //        '#delta' => $element['top']['actions']['actions']['edit_button']['#delta'],
      //        '#ajax' => [
      //          'callback' => 'Drupal\iucn_who_diff\Controller\DiffModalFormController::openModalForm',
      //          'wrapper' => $element['top']['actions']['actions']['edit_button']['#ajax']['wrapper'],
      //        ],
      //        '#access' => $paragraphs_entity->access('update'),
      //        '#paragraphs_mode' => 'diff',
      //        '#attributes' => [
      //          'class' => ['paragraphs-icon-button', 'paragraphs-icon-button-edit', 'use-ajax'],
      //          'title' => $this->t('Diff'),
      //        ],
      //        '#attached' => [
      //          'library' => ['core/drupal.dialog.ajax', 'core/jquery.form']
      //        ],
      //        '#id' => substr($element['top']['actions']['actions']['edit_button']['#id'], 0, -7) . 'diff-button'
      //      ];
    }

    $element['top']['summary'] = $containers;
    $count = count($containers) + 1;

    foreach ($components as $key => $component) {
      if (!empty($component['span']) && $component['span'] == 2) {
        $count++;
      }
    }

    $this->colCount = $count;

    $element['top']['#attributes']['class'][] = "paragraph-top-col-$count";

    $this->paragraphsEntity = $paragraphs_entity;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);
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
      $empty_message = $this->getSetting('empty_message');
      if (!empty($empty_message)) {
        $elements['empty_message'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['paragraph-summary-component', 'paragraph-empty']],
          'data' => ['#markup' => $empty_message],
        ];
      }
    }
    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $elements['#prefix'] = str_replace('paragraphs-tabs-wrapper', 'raw-paragraphs-tabs-wrapper', $elements['#prefix']);
    return $elements;
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
    $grouped_fields = $this->getGroupedFields();
    if ($this->getSetting('show_numbers') == 'yes') {
      $header['num'] = [
        'value' => $this->t('No.'),
        'span' => 1,
      ];
    }

    $components = $this->getFieldComponents($paragraph);
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
  public function getFieldComponents(ParagraphInterface $paragraph) {
    $bundle = $paragraph->getType();
    $form_display_mode = $this->getSetting('form_display_mode');
    if (empty($form_display_mode)) {
      $form_display_mode = 'default';
    }
    $components = EntityFormDisplay::load("paragraph.$bundle.$form_display_mode")
      ->getComponents();
    if (array_key_exists('moderation_state', $components)) {
      unset($components['moderation_state']);
    }
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
    $grouped_fields = $this->getGroupedFields();
    static $num = 0;
    if ($this->getSetting('show_numbers') == 'yes') {
      $num += 1;
      $summary['num']['value'] = $num;
    }

    $components = $this->getFieldComponents($paragraph);
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

      if ($field_type = $field_definition->getType() == 'entity_reference') {
        if ($paragraph->get($field_name)->entity && $paragraph->get($field_name)->entity->access('view label')) {
          $value = $paragraph->get($field_name)->entity->label();
          /** @var \Drupal\Core\Entity\Entity $entity */
          $entity = $paragraph->get($field_name)->entity;
          if (!$this->isHiddenTerm($entity)) {
            $label = $this->getEntityLabel($entity);
            $summary[$field_name]['value'] = $label;
          }
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

      $prefix = $this->getSummaryPrefix($field_name);
      if (!empty($prefix) && !empty($value)) {
        $value = $this->t("$prefix - @value", ['@value' => $value]);
      }

      $summary[$summary_field_name]['value'] = $value;
      if ($field_definition->getType() == 'string_long') {
        $summary[$summary_field_name]['span'] = 2;
      }
      else {
        $summary[$summary_field_name]['span'] = 1;
      }
    }

    foreach ($summary as &$component) {
      if (is_array($component['value'])) {
        foreach ($component['value'] as &$value) {
          $value = strip_tags($value);
        }
      }
      else {
        $component['value'] = strip_tags($component['value']);
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
  private function getGroupedFields() {
    return [
      'field_as_benefits_hab_trend' => [
        'grouped_with' => 'field_as_benefits_hab_level',
        'label' => $this->t('Habitat'),
      ],
      'field_as_benefits_pollut_trend' => [
        'grouped_with' => 'field_as_benefits_pollut_level',
        'label' => $this->t('Pollution'),
      ],
      'field_as_benefits_oex_trend' => [
        'grouped_with' => 'field_as_benefits_oex_level',
        'label' => $this->t('Overexploatation'),
      ],
      'field_as_benefits_climate_trend' => [
        'grouped_with' => 'field_as_benefits_climate_level',
        'label' => $this->t('Climate change'),
      ],
      'field_as_benefits_invassp_trend' => [
        'grouped_with' => 'field_as_benefits_invassp_level',
        'label' => $this->t('Invasive species'),
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
  private function getSummaryPrefix($field) {
    $prefixes = [
      'field_as_benefits_hab_trend' => 'Trend',
      'field_as_benefits_pollut_trend' => 'Trend',
      'field_as_benefits_oex_trend' => 'Trend',
      'field_as_benefits_climate_trend' => 'Trend',
      'field_as_benefits_invassp_trend' => 'Trend',
      'field_as_benefits_hab_level' => 'Impact level',
      'field_as_benefits_pollut_level' => 'Impact level',
      'field_as_benefits_oex_level' => 'Impact level',
      'field_as_benefits_climate_level' => 'Impact level',
      'field_as_benefits_invassp_level' => 'Impact level',
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
