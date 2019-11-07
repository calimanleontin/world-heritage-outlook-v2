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
      'lowercase' => FALSE,
      'paranthesis' => FALSE,
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
      $markup = $item->entity->name->value;
      if (!empty($item->entity->description->value)) {
        $markup .= $this->getSetting('separator');
        $description = strip_tags($item->entity->description->value);
        $description = "($description)";
        $markup .= $description;
      }

      if ($this->getSetting('lowercase') == TRUE) {
        $markup = strtolower($markup);
      }

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
      '#description' => $this->t('The string to separate the term name and description.'),
      '#default_value' => $this->getSetting('separator')
    ];

    $form['lowercase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use lowercase'),
      '#description' => $this->t('Check this to force lowercase on the resulting string.'),
      '#default_value' => $this->getSetting('lowercase')
    ];

    $form['lowercase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Description in paranthesis'),
      '#default_value' => $this->getSetting('paranthesis')
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

    $lowercase = $this->getSetting('lowercase') == TRUE ? $this->t('on') : $this->t('off');
    $summary[] = $this->t('Use lowercase: %lowercase', ['%lowercase' => $lowercase]);

    $paranthesis = $this->getSetting('paranthesis') == TRUE ? $this->t('yes') : $this->t('no');
    $summary[] = $this->t('Description paranthesis: %paranthesis', ['%paranthesis' => $paranthesis]);

    return $summary;
  }

}
