<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\field_group\FieldGroupFormatterBase;
use Drupal\Component\Utility\Unicode;

/**
 * Plugin implementation of the 'assessment_fieldset' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "assessment_fieldset",
 *   label = @Translation("Assessment Fieldset"),
 *   description = @Translation("This fieldgroup renders the inner content in a assessment_fieldset with the title as legend."),
 *   supported_contexts = {
 *     "form",
 *   }
 * )
 */
class AssessmentFieldset extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {

    $element += array(
      '#type' => 'assessment_fieldset',
      '#title' => Html::escape($this->t($this->getLabel())),
      '#pre_render' => array(),
      '#attributes' => array(),
      '#fields_titles' => $this->getSetting('fields_titles'),
    );

    if ($this->getSetting('description')) {
      $element += array(
        '#description' => $this->getSetting('description'),
      );

      // When a fieldset has a description, an id is required.
      if (!$this->getSetting('id')) {
        $element['#id'] = Html::getId($this->group->group_name);
      }

    }
    $tab = \Drupal::request()->query->get('tab');
    if ($tab == 'conservation-outlook') {
      $element['headings'] = [
        'topic' => [
          "#type" => "markup",
          "#markup" => '<div class="overall-row-view overall-header"><div>' . $this->t('Topic') . '</div>',
        ],
        'justification' => [
          "#type" => "markup",
          "#markup" => '<div>' . $this->t('Justification of assessment') . '</div>',
        ],
        'assessment' => [
          "#type" => "markup",
          "#markup" => '<div>' . $this->t('Assessment') . '</div></div>',
        ]
      ];
    }

    if ($this->getSetting('id')) {
      $element['#id'] = Html::getId($this->getSetting('id'));
    }

    $classes = $this->getClasses();
    if (!empty($classes)) {
      $element['#attributes'] += array('class' => $classes);
    }

    if ($this->getSetting('required_fields')) {
      $element['#attached']['library'][] = 'field_group/formatter.fieldset';
      $element['#attached']['library'][] = 'field_group/core';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {

    $form = parent::settingsForm();

    $form['description'] = array(
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('description'),
      '#weight' => -4,
    );

    $form['fields_titles'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Fields titles'),
      '#descirption' => $this->t('Field names separated by | to show as header'),
      '#default_value' => $this->getSetting('fields_titles'),
    );

    if ($this->context == 'form') {
      $form['required_fields'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Mark group as required if it contains required fields.'),
        '#default_value' => $this->getSetting('required_fields'),
        '#weight' => 2,
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    if ($this->getSetting('required_fields')) {
      $summary[] = $this->t('Mark as required');
    }

    if ($this->getSetting('description')) {
      // Avoid long description break manage form display page.
      $description = $this->getSetting('description');
      if (strlen($description) > 100) {
        $description = Unicode::truncate($description, 100);
        $description .= '...';
      }

      $summary[] = $this->t('Description : @description',
        array('@description' => $description)
      );
    }

    if ($this->getSetting('fields_titles')) {
      $summary[] = $this->t('Header titles : @fields_titles', ['@'=>$this->getSetting('fields_titles')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = array(
        'description' => '',
        'fields_titles' => '',
    ) + parent::defaultSettings($context);

    if ($context == 'form') {
      $defaults['required_fields'] = 1;
    }

    return $defaults;
  }

}
