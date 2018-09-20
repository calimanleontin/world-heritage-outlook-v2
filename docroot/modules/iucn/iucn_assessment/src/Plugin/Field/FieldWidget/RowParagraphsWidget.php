<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Utility\Unicode;

/**
 * Plugin implementation of the 'entity_reference paragraphs' widget.
 *
 * We hide add / remove buttons when translating to avoid accidental loss of
 * data because these actions effect all languages.
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
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $paragraphs_entity = NULL;
    $widget_state = static::getWidgetState($parents, $field_name, $form_state);

    $paragraphs_entity = $widget_state['paragraphs'][$delta]['entity'];
    $summary = $this->getSummaryComponents($paragraphs_entity);
    $summary_row = [
      '#type' => 'table',
      '#header' => array_keys($summary),
      '#rows' => [$summary],
      '#attributes' => ['class' => ['table', 'table-responsive']],
    ];

    unset($element['top']['type']);
    unset($element['top']['icons']);

    $element['top']['summary'] = $summary_row;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $parents = $elements[0]['#field_parents'];

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraphs_entity */
    $paragraphs_entity = NULL;
    $widget_state = static::getWidgetState($parents, $field_name, $form_state);
    $paragraphs_entity = $widget_state['paragraphs'][0]['entity'];
    $components = $this->getSummaryComponents($paragraphs_entity);
    array_unshift($components, ['xd' => 'xd']);
    $header = [
      '#type' => 'table',
      '#rows' => [],
      '#header' => array_keys($components),
    ];
    array_unshift($elements, $header);

    $elements['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    return $elements;
  }


  /**
   * {@inheritdoc}
   */
  public function getSummaryComponents(ParagraphInterface $paragraph) {
    $show_behavior_summary = isset($options['show_behavior_summary']) ? $options['show_behavior_summary'] : TRUE;
    $paragraph->summaryCount = 0;
    $summary = [];
    $components = entity_get_form_display('paragraph', $paragraph->getType(), 'default')->getComponents();
    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach (array_keys($components) as $field_name) {
      // Components can be extra fields, check if the field really exists.
      if (!$paragraph->hasField($field_name)) {
        continue;
      }
      $field_definition = $paragraph->getFieldDefinition($field_name);
      // @todo: Call the field label service in order to get the proper labels for certain cycles.
      $label = $field_definition->getLabel();
      // We do not add content to the summary from base fields, skip them
      // keeps performance while building the paragraph summary.
      if (!($field_definition instanceof FieldConfigInterface) || !$paragraph->get($field_name)->access('view')) {
        continue;
      }

      if ($field_definition->getType() == 'image' || $field_definition->getType() == 'file') {
        $file_summary = $paragraph->getFileSummary($field_name);
        if ($file_summary != '') {
          $summary[$label] = $file_summary;
        }
      }

      $text_summary = $this->getTextSummary($paragraph, $field_name, $field_definition);
      if ($text_summary != '') {
        $summary[$label] = $text_summary;
      }

      if ($field_definition->getType() == 'entity_reference_revisions') {
        // Decrease the depth, since we are entering a nested paragraph.
        $nested_summary = $this->getNestedSummary($paragraph, $field_name);
        if ($nested_summary != '') {
          $summary[$label] = $nested_summary;
        }
      }

      if ($field_type = $field_definition->getType() == 'entity_reference') {
        if ($paragraph->get($field_name)->entity && $paragraph->get($field_name)->entity->access('view label')) {
          $summary[$label] = $paragraph->get($field_name)->entity->label();
        }
      }

      // Add the Block admin label referenced by block_field.
      if ($field_definition->getType() == 'block_field') {
        if (!empty($paragraph->get($field_name)->first())) {
          $block_admin_label = $paragraph->get($field_name)->first()->getBlock()->getPluginDefinition()['admin_label'];
          $summary[$label] = $block_admin_label;
        }
      }

      if ($field_definition->getType() == 'link') {
        if (!empty($paragraph->get($field_name)->first())) {
          // If title is not set, fallback to the uri.
          if ($title = $paragraph->get($field_name)->title) {
            $summary[$label] = $title;
          }
          else {
            $summary[$label] = $paragraph->get($field_name)->uri;
          }
        }
      }
    }

    if ($show_behavior_summary) {
      $paragraphs_type = $paragraph->getParagraphType();
      foreach ($paragraphs_type->getEnabledBehaviorPlugins() as $plugin_id => $plugin) {
        if ($plugin_summary = $plugin->settingsSummary($paragraph)) {
          $summary = array_merge($summary, $plugin_summary);
        }
      }
    }

    foreach ($summary as &$value) {
      $value = strip_tags($value);
    }
    return $summary;
  }


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
      if (strlen($text) > 150) {
        $text = Unicode::truncate($text, 150);
        $text .= '...';
      }
      $summary = $text;
    }

    return trim($summary);
  }

  protected function getNestedSummary(ParagraphInterface $paragraph, $field_name) {
    $summary = [];

    foreach ($paragraph->get($field_name) as $item) {
      $entity = $item->entity;
      if ($entity instanceof ParagraphInterface) {
        $summary_components = $this->getSummaryComponents($entity);
        $first_component = reset($summary_components);
        $summary[] = $first_component;

      }
    }

    if (empty($summary)) {
      return NULL;
    }

    $paragraph_summary = implode(', ', $summary);
    return $paragraph_summary;
  }

}
