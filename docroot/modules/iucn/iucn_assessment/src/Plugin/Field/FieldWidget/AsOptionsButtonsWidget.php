<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin implementation of the 'assessment_options_buttons' widget.
 *
 * @FieldWidget(
 *   id = "assessment_options_buttons",
 *   label = @Translation("Assessment Check boxes/radio buttons"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class AsOptionsButtonsWidget extends OptionsWidgetBase {

  /**
   * The main terms.
   *
   * @var array
   */
  protected $groups;

  /**
   * Groups with no children.
   *
   * @var array
   */
  protected $empty_groups;

  /**
   * The parent node.
   *
   * @var \Drupal\Node\NodeInterface
   */
  protected $parentNode;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'select_title' => '',
      'select_label' => '',
      'checkboxes_label' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['select_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option title'),
      '#default_value' => $this->getSetting('select_title'),
    ];
    $elements['select_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Select label'),
      '#default_value' => $this->getSetting('select_label'),
    ];
    $elements['checkboxes_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Checkboxes label'),
      '#default_value' => $this->getSetting('checkboxes_label'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $checkboxes_label = $this->getSetting('checkboxes_label');
    $select_title = $this->getSetting('select_title');
    $select_label = $this->getSetting('select_label');
    if (!empty($checkboxes_label)) {
      $summary[] = $this->t('Checkboxes label: @checkboxes_label', ['@checkboxes_label' => $checkboxes_label]);
    }
    if (!empty($select_title)) {
      $summary[] = $this->t('Select option title: @select_title', ['@select_title' => $select_title]);
    }
    if (!empty($select_label)) {
      $summary[] = $this->t('Select label: @select_label', ['@select_label' => $select_label]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    $states = $element['options_groups']['#states'];
    unset($element['options_groups']['#states']);

    foreach ($element as $key => $item) {
      if (!is_array($item) || empty($item['#type']) || ($item['#type'] != 'checkbox')) {
        continue;
      }
      $value = -1;
      if (!empty($states[$item["#return_value"]])) {
        $value = $states[$item["#return_value"]];
      }
      $id = explode('--', $element['options_groups']['#id']);
      $element[$key]['#states'] = array(
        'visible' => array(
          ':input[data-drupal-selector="' . $id[0] . '"]' => array('value' => $value),
        ),
      );
    }

    $parents = $element['#field_parents'];
    $field_name = $element['#field_name'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['array_parents'] = $element['#array_parents'];
    static::setWidgetState($parents, $field_name, $form_state, $field_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function isHiddenTerm($tid) {
    return empty(Term::load($tid)->label());
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->parentNode = \Drupal::routeMatch()->getParameter('node');
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $all_options = $this->getOptions($items->getEntity());
    $this->groups = [];
    $this->groups[0] = $this->getSetting('select_title');
    $options = [0 => ''];
    $states = [];
    $current_id = NULL;
    $this->empty_groups = [];
    foreach ($all_options as $tid => $title) {
      if ($this->isHiddenTerm($tid)) {
        continue;
      }
      $options[$tid] = $title;
      if (strpos($title, '-') === FALSE) {
        $current_id = $tid;
        $this->groups[$tid] = $title;
        $this->empty_groups[$tid] = $tid;
      }
      else {
        $states[$tid] = $current_id;
        unset($this->empty_groups[$current_id]);
      }
      $options[$tid] = ltrim($title, '-');
    }

    $selected = $this->getSelectedOptions($items);

    // If required and there is one single option, preselect it.
    if ($this->required && count($options) == 1) {
      reset($options);
      $selected = [key($options)];
    }

    $element += [
      '#type' => 'checkboxes',
      '#default_value' => $selected,
      '#options' => $options,
    ];

    $default_value = 0;
    foreach ($selected as $key => $value) {
      if ($states[$value]) {
        $default_value = $states[$value];
        break;
      }
    }

    if (empty($default_value) && !empty($selected)) {
      $default_value = reset($selected);
    }
    $element['#title'] = '';
    $element['options_groups'] = array(
      '#type' => 'select',
      '#options' => $this->groups,
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => ['options-groups'],
        'data-id' => 'options-groups',
      ],
      '#states' => $states,
      '#empty_groups' => $this->empty_groups,
    );

    $element['options_groups']['#prefix'] = '<div>'.
      '<div class="label">' . $this->getSetting('select_label') . '</div>' .
      '<div class="form-data">';
    $element['options_groups']['#suffix'] = '</div>'.
      '</div>'.
      '<div>'.
      '<div class="label as-checkboxes-label">' . $this->getSetting('checkboxes_label') . '</div>'.
      '<div class="form-data">';
    $element['checkboxes_group_close'] = array(
      '#weight' => 99,
      '#markup' => '</div></div>',
    );
    $this->states = $states;

    $element['#prefix'] = '<div class="as-checkboxes">';
    $element['#suffix'] = '</div>';
    $element['#attached']['library'][] = 'iucn_assessment/iucn_assessment.option_buttons';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      if (isset($this->groups[$value['target_id']]) && empty($this->empty_groups[$value['target_id']])) {
        unset($values[$key]);
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if (!$this->required && !$this->multiple) {
      return t('N/A');
    }
  }

}
