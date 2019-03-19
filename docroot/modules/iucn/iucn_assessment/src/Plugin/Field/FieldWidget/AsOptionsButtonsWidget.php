<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class AsOptionsButtonsWidget extends OptionsWidgetBase implements ContainerFactoryPluginInterface {

  /** @var \Drupal\taxonomy\TermStorageInterface */
  protected $termStorage;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * The main terms.
   *
   * @var array
   */
  protected $groups;

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
      $element[$key]['#states'] = [
        'visible' => [
          ':input[data-drupal-selector="' . $id[0] . '"]' => ['value' => $value],
        ],
      ];
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $all_options = $this->getOptions($items->getEntity());
    $this->groups = [];
    $this->groups[0] = $this->getSetting('select_title');
    $options = [0 => ''];
    $states = [];
    $current_id = NULL;
    $last_parent_title = '';

    foreach ($all_options as $tid => $title) {
      // Hidden child term.
      if ($title == '-') {
        continue;
      }

      $options[$tid] = $title;
      if ($this->isParentTerm($tid)) {
        $current_id = $tid;
        $last_parent_title = $title;
      }
      else {
        // Hidden parent term so we ignore the child.
        if ($last_parent_title == '') {
          continue;
        }
        // At least one child was found for a parent category, we can now list it as an option.
        $this->groups[$current_id] = $last_parent_title;
        $states[$tid] = $current_id;
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
      if (!empty($states[$value])) {
        $default_value = $states[$value];
        break;
      }
    }

    if (empty($default_value) && !empty($selected)) {
      $default_value = reset($selected);
    }
    $element['#title'] = '';
    $element['options_groups'] = [
      '#type' => 'select',
      '#options' => $this->groups,
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => ['options-groups'],
        'data-id' => 'options-groups',
      ],
      '#states' => $states,
    ];

    $element['options_groups']['#prefix'] = '<div>' .
      '<div class="label">' . $this->getSetting('select_label') . '</div>' .
      '<div class="form-data">';
    $element['options_groups']['#suffix'] = '</div>' .
      '</div>' .
      '<div>' .
      '<div class="label as-checkboxes-label">' . $this->getSetting('checkboxes_label') . '</div>' .
      '<div class="form-data">';
    $element['checkboxes_group_close'] = [
      '#weight' => 99,
      '#markup' => '</div></div>',
    ];
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
      if (isset($this->groups[$value['target_id']])) {
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

  /**
   * @param $tid
   * @return bool
   */
  protected function isParentTerm($tid) {
    return empty($this->termStorage->loadParents($tid));
  }

}
