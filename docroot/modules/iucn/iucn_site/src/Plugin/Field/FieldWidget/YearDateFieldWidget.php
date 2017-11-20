<?php

namespace Drupal\iucn_site\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'year_date_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "year_date_field_widget",
 *   label = @Translation("Year"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class YearDateFieldWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = [];
    $years = ['0001-01-01' => t('- None -')];
    for ($i = date("Y") + 10; $i > 1; $i--) {
      $year = str_pad($i, 4, "0", STR_PAD_LEFT);
      $years["$year-01-01"] = $i;
    }
    $element['value'] = $element + array(
      '#type' => 'select',
      '#options' => $years,
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
    );
    $element['value']['#title'] = $this->fieldDefinition->getLabel();
    $element['value']['#description'] = $this->fieldDefinition->getDescription();

    return $element;
  }

}
