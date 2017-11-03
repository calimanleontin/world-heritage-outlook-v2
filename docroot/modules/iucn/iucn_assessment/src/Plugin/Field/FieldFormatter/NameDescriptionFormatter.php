<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;


/**
 * Plugin implementation of the 'Name with description' field formatter..
 *
 * @FieldFormatter(
 *   id = "name_description",
 *   label = @Translation("Name with description"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class NameDescriptionFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'separator' => ' - ',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      if (empty($item->entity)) {
        continue;
      }
      $markup = $this->t($item->entity->name->value);
      $markup .= $this->getSetting('separator');
      $markup .= strip_tags($this->t($item->entity->description->value));
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#description' => $this->t('The string to separate the term name and description'),
      '#default_value' => $this->getSetting('separator')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($separator = $this->getSetting('separator')) {
      $summary[] = $this->t('Separator: "%separator"', ['%separator' => $separator]);
    }

    return $summary;
  }

}
