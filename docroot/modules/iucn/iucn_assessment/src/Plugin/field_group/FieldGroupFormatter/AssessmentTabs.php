<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\iucn_assessment\Element\AssessmentHorizontalTabs;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'assessment_horizontal_tabs' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "assessment_tabs",
 *   label = @Translation("Assessment Tabs"),
 *   description = @Translation("This fieldgroup renders child groups in its
 *   own tabs wrapper."), supported_contexts = {
 *     "form",
 *   }
 * )
 */
class AssessmentTabs extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    $element += [
      '#prefix' => '<div class=" ' . implode(' ', $this->getClasses()) . '">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#parents' => [$this->group->group_name],
      '#default_tab' => '',
    ];

    if ($this->getSetting('id')) {
      $element['#id'] = Html::getId($this->getSetting('id'));
    }

    // By default tabs don't have titles but you can override it in the theme.
    if ($this->getLabel()) {
      $element['#title'] = Html::escape($this->getLabel());
    }

    $form_state = new FormState();

    $element += [
      '#type' => 'assessment_horizontal_tabs',
      '#theme_wrappers' => ['assessment_horizontal_tabs'],
    ];
    $on_form = $this->context == 'form';

    $element = AssessmentHorizontalTabs::processHorizontalTabs($element, $form_state, $on_form);

    // Make sure the group has 1 child. This is needed to succeed at form_pre_render_vertical_tabs().
    // Skipping this would force us to move all child groups to this array, making it an un-nestable.
    $element['group']['#groups'][$this->group->group_name] = [0 => []];
    $element['group']['#groups'][$this->group->group_name]['#group_exists'] = TRUE;

    // Search for a tab that was marked as open. First one wins.
    foreach (Element::children($element) as $tab_name) {
      if (!empty($element[$tab_name]['#open'])) {
        $element[$this->group->group_name . '__active_tab']['#default_value'] = $tab_name;
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {

    $classes = parent::getClasses();
    $classes[] = 'field-group-' . $this->group->format_type . '-wrapper';

    return $classes;
  }

}
