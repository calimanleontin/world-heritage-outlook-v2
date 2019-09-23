<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\field_group\Plugin\field_group\FieldGroupFormatter\Details;

/**
 * Details element.
 *
 * @FieldGroupFormatter(
 *   id = "details_assessment",
 *   label = @Translation("Details assessment"),
 *   description = @Translation("Details with terms next to title"),
 *   supported_contexts = {
 *     "view"
 *   }
 * )
 */
class DetailsAssessment extends Details {

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
          $element['#terms'][$field_name] = $field_name;
          hide($element[$field_name]);
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
      '#description' => $this->t('Field machine names separated by | to render next to title'),
      '#default_value' => $this->getSetting('terms'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = array(
      'terms' => '',
    ) + parent::defaultSettings($context);

    return $defaults;
  }

}
