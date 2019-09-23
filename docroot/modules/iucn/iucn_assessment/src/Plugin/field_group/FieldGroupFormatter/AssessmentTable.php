<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * List of possible wrapper types for the table
 */
const WRAPPERSLIST = ['fieldset', 'details'];

/**
 * Plugin implementation of the 'assessment_table' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "assessment_table",
 *   label = @Translation("Assessment Table"),
 *   description = @Translation("This fieldgroup renders the inner content in a table."), supported_contexts = {
 *     "form",
 *   }
 * )
 */
class AssessmentTable extends FieldGroupFormatterBase {


  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = [
        'wrapper' => key(WRAPPERSLIST),
      ] + parent::defaultSettings($context);
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();

    $form['wrapper'] = [
      '#type' => 'select',
      '#options' => WRAPPERSLIST,
      '#title' => $this->t('Wrapper'),
      '#default_value' => $this->getSetting('wrapper'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    if (is_string(WRAPPERSLIST[$this->getSetting('wrapper')])) {
      $element['#type'] = WRAPPERSLIST[$this->getSetting('wrapper')];
    }
    $element['#title'] = $this->group->label;

    $table = [];
    $table += [
      '#type' => 'table',
      '#title' => Html::escape($this->t($this->getLabel())),
    ];

    if ($this->getSetting('id')) {
      $element['#id'] = Html::getId($this->getSetting('id'));
    }

    $classes = $this->getClasses();
    $classes[] = 'table-field-group';
    if (!empty($classes)) {
      $element += [
        '#attributes' => ['class' => $classes],
      ];
    }

    $table['#empty'] = t('There is nothing to display');

    $fields = Element::children($element);
    $rows = [];
    $header = [];
    foreach ($fields as $key) {
      $header[] = render($element[$key]['#title']);
      if (!empty($element[$key]['widget'][0]['value']['#title'])) {
        $element[$key]['widget'][0]['value']['#title'] = '';
      }
      if (!empty($element[$key]['widget']['#title'])) {
        $element[$key]['widget']['#title'] = '';
      }
      $rows['#cells'][] = render($element[$key]);
      unset($element[$key]);
    }
    $table['#header'] = $header;
    $table['#rows'] = $rows;

    $element['fields_in_table'] = $table;
  }

}
