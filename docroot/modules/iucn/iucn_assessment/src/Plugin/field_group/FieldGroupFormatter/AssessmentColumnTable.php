<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'assessment_column_table' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "assessment_column_table",
 *   label = @Translation("Assessment Column Table"),
 *   description = @Translation("This fieldgroup renders the inner content in a column table."), supported_contexts = {
 *     "form",
 *   }
 * )
 */
class AssessmentColumnTable extends FieldGroupFormatterBase {

  function prepareColumnFields($element) {
    $start_fields = [];
    if (!empty($this->getSetting('start_fields'))) {
      $start_fields = explode('|', $this->getSetting('start_fields'));
    }
    $columns = [];
    if (!empty($this->getSetting('columns'))) {
      $columns = explode("\n", $this->getSetting('columns'));
    }
    $rows = [];
    if (!empty($this->getSetting('rows'))) {
      $rows = explode("\n", $this->getSetting('rows'));
    }
    $captions = [];
    if (!empty($this->getSetting('captions'))) {
      $captions = explode("\n", $this->getSetting('captions'));
    }

    $fields = Element::children($element);

    $result = [];
    foreach ($start_fields as $idx => $start_field) {
      $caption = "";
      if (!empty($captions[$idx])) {
        $caption = $captions[$idx];
      }
      $current_columns = [];
      $current_rows = [];
      if (!empty($columns[$idx])) {
        $current_columns = explode("|", $columns[$idx]);
      }
      if (!empty($rows[$idx])) {
        $current_rows = explode("|", $rows[$idx]);
      }
      $require_fields = count($current_columns) * count($current_rows);

      $pos = array_search($start_field, $fields);

      $left = array_slice($fields, 0, $pos);
      $right = array_slice($fields, $pos);
      if ($left) {
        $result += array_flip($left);
      }
      $needed = array_slice($right, 0, $require_fields);
      $fields = array_slice($right, $require_fields);
      if ($needed) {
        $result[$start_field] = [
          'fields' => $needed,
          'settings' => [
            'columns' => $current_columns,
            'rows' => $current_rows,
            'caption' => $caption,
          ],
        ];
      }
    }
    $result += array_flip($fields);
    return $result;
  }

  function createColumnTable(&$element, $values) {
    $fields = $values['fields'];
    $table = [
      '#type' => 'table',
      '#title' => Html::escape($this->getLabel()),
      '#empty' => t('There is nothing to display'),
    ];
    if ($values['settings']['caption']) {
      $table += array(
        '#caption' => t($values['settings']['caption']),
      );
    }
    $rows = [];
    $header = [];
    $header[] = [
      'class' => ['description-row'],
      'data' => '',
    ];
    foreach ($values['settings']['columns'] as $column_name) {
      $header[] = $column_name;
    }

    foreach ($values['settings']['rows'] as $row_name) {
      $row = [];
      $row[] = [
        'class' => ['description-row'],
        'data' => $row_name,
      ];
      foreach ($values['settings']['columns'] as $column_name) {
        $key = array_shift($fields);
        if (!$key) {
          continue;
        }
        if (!empty($element[$key]['widget'][0]['value']['#title'])) {
          $element[$key]['widget'][0]['value']['#title'] = '';
        }
        if (!empty($element[$key]['widget']['#title'])) {
          $element[$key]['widget']['#title'] = '';
        }
        $row[] = ['data' => render($element[$key])];
        unset($element[$key]);
      }
      $rows[] = $row;
    }
    $table['#header'] = $header;
    $table['#rows'] = $rows;
    $row = [];
    $row['table'] = render($table);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);
    $element['#type'] = 'fieldset';
    if ($this->getSetting('id')) {
      $element['#id'] = Html::getId($this->getSetting('id'));
    }
    $classes = $this->getClasses();
    $classes[] = 'table-field-group column-table vertical-table';
    if (!empty($classes)) {
      $element += [
        '#attributes' => ['class' => $classes],
      ];
    }

    $fields = $this->prepareColumnFields($element);
    $table = [
      '#type' => 'table',
      '#title' => Html::escape($this->getLabel()),
      '#empty' => t('There is nothing to display'),
    ];
    $rows = [];
    $header = [];

    foreach ($fields as $key => $values) {
      if (is_array($values)) {
        $rows[] = $this->createColumnTable($element, $values);
      }
      else {
        $row = [];
        $row['rendered'] = [
          'class' => ['redirect-table__path'],
          'data' => render($element[$key]),
        ];
        $rows[] = $row;
      }
      unset($element[$key]);
    }
    $table['#header'] = $header;
    $table['#rows'] = $rows;
    $element['label_field_table'] = $table;

    $element['#attached']['library'][] = 'iucn_assessment/iucn_assessment.table_field_group';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = [
        'start_fields' => '',
        'rows' => '',
        'columns' => '',
        'captions' => '',
      ] + parent::defaultSettings();
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();

    $form['start_fields'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Start Fields'),
      '#descirption' => $this->t('Field machine names separated by |'),
      '#default_value' => $this->getSetting('start_fields'),
    );
    $form['captions'] = array(
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#descirption' => $this->t('Descriptions separated by new line'),
      '#default_value' => $this->getSetting('captions'),
      '#weight' => -4,
    );
    $form['columns'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Columns'),
      '#descirption' => $this->t('Field machine names separated by | and new line to render as column title'),
      '#default_value' => $this->getSetting('columns'),
    );
    $form['rows'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Rows'),
      '#descirption' => $this->t('Field machine names separated by | and new line to render as row label'),
      '#default_value' => $this->getSetting('rows'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    if ($this->getSetting('start_fields')) {
      $summary[] = $this->t('Start fields : @start_fields',
        array('@start_fields' => $this->getSetting('start_fields'))
      );
    }
    if ($this->getSetting('captions')) {
      $summary[] = $this->t('Description : @captions',
        array('@captions' => $this->getSetting('captions'))
      );
    }
    if ($this->getSetting('columns')) {
      $summary[] = $this->t('Columns : @columns',
        array('@columns' => $this->getSetting('columns'))
      );
    }
    if ($this->getSetting('rows')) {
      $summary[] = $this->t('Rows : @rows',
        array('@rows' => $this->getSetting('rows'))
      );
    }

    return $summary;
  }

}
