<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Template\Attribute;
use Drupal\field_group\FieldGroupFormatterBase;
use Drupal\field_group\Plugin\field_group\FieldGroupFormatter\HtmlElement;

/**
 * Plugin implementation of the 'html_element' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "html_element_assessment",
 *   label = @Translation("HTML element assessment"),
 *   description = @Translation("This fieldgroup renders the inner content in a HTML element with classes and attributes."),
 *   supported_contexts = {
 *     "form",
 *     "view",
 *   }
 * )
 */
class HtmlElementAssessment extends HtmlElement {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    $field_names = $this->getSetting('terms');
    if (!empty($field_names)) {
      $field_names = explode('|', $field_names);
      foreach ($field_names as $field_name) {
        if (isset($element[$field_name])) {
          if (isset($element[$field_name][0])) {
            $element['#terms'][$field_name] = $field_name;
            hide($element[$field_name]);
          }
          else {
            unset($element[$field_name]);
          }
        }
      }
    }
    if ($this->getSetting('show_fields_preview')) {
      $label = '<div>' . $this->label . '</div>';
      foreach($element as $key => $container) {
        if (is_array($container) && !empty($container['#type']) && $container['#type'] == 'container') {
          $markup = '';
          $widget = $container['widget'];
          if (!empty($widget[0]['value'])) {
            $value = $widget[0]['value'];
            if ($value["#type"] == 'textarea') {
              $markup = $value['#value'];
            }
          }
          elseif (!empty($widget['#value'])) {
            if ($widget["#type"] == 'select') {
              $markup = [];
              foreach($widget['#value'] as $option) {
                $markup[] = $widget['#options'][$option];
              }
              $markup = implode(', ', $markup);
            }
          }
          $element[$key] = [
            '#type' => 'markup',
            '#markup' => $label . '<div>' . $markup . '</div>',
          ];
          $label = '';
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {

    $form = parent::settingsForm();

    $form['terms'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Terms'),
      '#descirption' => $this->t('Field machine names separated by | to render next to title'),
      '#default_value' => $this->getSetting('terms'),
    );

    $form['show_fields_preview'] = array(
      '#title' => $this->t('Show fields preview'),
      '#type' => 'select',
      '#options' => array(0 => $this->t('No'), 1 => $this->t('Yes')),
      '#default_value' => $this->getSetting('show_fields_preview'),
      '#weight' => 2,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    if ($this->getSetting('terms')) {
      $summary[] = $this->t('Terms') . ' ' . $this->getSetting('terms');
    }

    if ($this->getSetting('show_fields_preview')) {
      $summary[] = $this->t('Fields preview mode');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = array(
      'terms' => '',
      'show_fields_preview' => 0,
    ) + parent::defaultSettings($context);

    return $defaults;

  }

}
