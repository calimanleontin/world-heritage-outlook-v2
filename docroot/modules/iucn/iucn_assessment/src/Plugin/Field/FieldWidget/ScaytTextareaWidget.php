<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;

/**
 * Plugin implementation of the 'text_textarea' widget.
 *
 * @FieldWidget(
 *   id = "text_textarea_with_scayt",
 *   label = @Translation("Text area with scayt (multiple rows)"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class ScaytTextareaWidget extends TextareaWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $main_widget = parent::formElement($items, $delta, $element, $form, $form_state);

    $main_widget['#type'] = 'text_format';
    $main_widget['#allowed_formats'] = ['simple_text_formater_with_scayt'];
    $main_widget['#after_build'][] = [$this, 'remove_textarea_help'];

    unset($main_widget['#base_type']);
    $main_widget['#default_value'] = nl2br($main_widget['#default_value']);

    return $main_widget;
  }

  public function remove_textarea_help($form_element, FormStateInterface $form_state) {
    if (isset($form_element['format'])) {
      unset($form_element['format']['guidelines']);
      unset($form_element['format']['help']);
      unset($form_element['format']['#type']);
      unset($form_element['format']['#theme_wrappers']);
    }

    return $form_element;
  }

}
