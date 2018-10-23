<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;

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
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'title' => t('Paragraph'),
      'title_plural' => t('Paragraphs'),
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '',
      'features' => [],
    );
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

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $paragraphs_entity = NULL;
    $widget_state = static::getWidgetState($parents, $field_name, $form_state);

    $paragraphs_entity = $widget_state['paragraphs'][$delta]['entity'];
    $components = $this->getSummaryComponents($paragraphs_entity);
    $containers = $this->getSummaryContainers($components);

    $element['top']['actions']['#weight'] = 9999;
    if(!empty($element['top']['actions']['actions']['edit_button'])) {
      // Create the custom 'Diff' button
      $element['top']['actions']['actions']['diff_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Diff'),
        '#name' => substr($element['top']['actions']['actions']['edit_button']['#name'], 0 , -4) . 'diff',
        '#weight' => 2,
        '#delta' => $element['top']['actions']['actions']['edit_button']['#delta'],
        '#ajax' => [
          'callback' => 'Drupal\iucn_assessment\Controller\DiffModalFormController::openDiffModalForm',
          'wrapper' => $element['top']['actions']['actions']['edit_button']['#ajax']['wrapper'],
        ],
        '#access' => $paragraphs_entity->access('update'),
        '#paragraphs_mode' => 'diff',
        '#attributes' => [
          'class' => ['paragraphs-icon-button', 'paragraphs-icon-button-edit', 'use-ajax'],
          'title' => $this->t('Diff'),
        ],
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax', 'core/jquery.form']
        ],
        '#id' => substr($element['top']['actions']['actions']['edit_button']['#id'], 0, -7) . 'diff-button'
      ];
    }

    $element['top']['summary'] = $containers;
    $count = count($containers) + 1;

    foreach ($components as $key => $component) {
      if ($component['span'] == 2) {
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
      $header_containers = $this->getSummaryContainers($header_components);
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
    $prefix = $elements['#prefix'];
    $prefix = str_replace('is-horizontal paragraphs-tabs-wrapper', 'relative-wrapper is-horizontal paragraphs-tabs-wrapper', $prefix);
    $elements['#prefix'] = $prefix;
    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
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

    $components = $this->getFieldComponents($paragraph);
    foreach (array_keys($components) as $field_name) {
      if (!$paragraph->hasField($field_name)) {
        continue;
      }
      $field_definition = $paragraph->getFieldDefinition($field_name);
      $header[$field_name]['value'] = $field_definition->getLabel();
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
      $span = $component['span'];
      $containers[$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'paragraph-summary-component',
            "paragraph-summary-component-$key",
            "paragraph-summary-component-span-$span",
          ],
        ],
        'data' => ['#markup' => $component['value']],
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
    $components = EntityFormDisplay::load("paragraph.$bundle.default")->getComponents();
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
    $components = $this->getFieldComponents($paragraph);
    foreach (array_keys($components) as $field_name) {
      // Components can be extra fields, check if the field really exists.
      if (!$paragraph->hasField($field_name)) {
        continue;
      }
      $field_definition = $paragraph->getFieldDefinition($field_name);
      // We do not add content to the summary from base fields, skip them
      // keeps performance while building the paragraph summary.
      if (!($field_definition instanceof FieldConfigInterface) || !$paragraph->get($field_name)->access('view')) {
        continue;
      }

      $text_summary = $this->getTextSummary($paragraph, $field_name, $field_definition);
      $summary[$field_name]['value'] = $text_summary;

      if ($field_definition->getType() == 'image' || $field_definition->getType() == 'file') {
        $summary[$field_name]['value'] = $paragraph->getFileSummary($field_name);
      }

      if ($field_definition->getType() == 'entity_reference_revisions') {
        $summary[$field_name]['value'] = $this->getNestedSummary($paragraph, $field_name);
      }

      if ($field_type = $field_definition->getType() == 'entity_reference') {
        if ($paragraph->get($field_name)->entity && $paragraph->get($field_name)->entity->access('view label')) {
          $summary[$field_name]['value'] = $paragraph->get($field_name)->entity->label();
        }
      }

      // Add the Block admin label referenced by block_field.
      if ($field_definition->getType() == 'block_field') {
        if (!empty($paragraph->get($field_name)->first())) {
          $block_admin_label = $paragraph->get($field_name)->first()->getBlock()->getPluginDefinition()['admin_label'];
          $summary[$field_name]['value'] = $block_admin_label;
        }
      }

      if ($field_definition->getType() == 'link') {
        if (!empty($paragraph->get($field_name)->first())) {
          // If title is not set, fallback to the uri.
          if ($title = $paragraph->get($field_name)->title) {
            $summary[$field_name]['value'] = $title;
          }
          else {
            $summary[$field_name]['value'] = $paragraph->get($field_name)->uri;
          }
        }
      }

      if ($field_definition->getType() == 'string_long') {
        $summary[$field_name]['span'] = 2;
      }
      else {
        $summary[$field_name]['span'] = 1;
      }
    }

    foreach ($summary as &$value) {
      $value['value'] = strip_tags($value['value']);
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
        $summary[] = $first_component['value'];
      }
    }

    if (empty($summary)) {
      return '';
    }

    $paragraph_summary = implode(', ', $summary);
    return $paragraph_summary;
  }

}
