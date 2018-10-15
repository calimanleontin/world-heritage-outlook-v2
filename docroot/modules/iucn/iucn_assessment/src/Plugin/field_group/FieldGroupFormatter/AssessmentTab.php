<?php

namespace Drupal\iucn_assessment\Plugin\field_group\FieldGroupFormatter;

use Drupal\field_group\Plugin\field_group\FieldGroupFormatter\Tab;

/**
 * Plugin implementation of the 'assessment_horizontal_tab' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "assessment_tab",
 *   label = @Translation("Assessment Tab"),
 *   description = @Translation("This fieldgroup renders the content as a tab."),
 *   format_types = {
 *     "open",
 *     "closed",
 *   },
 *   supported_contexts = {
 *     "form",
 *   }
 * )
 */
class AssessmentTab extends Tab {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);
    $request = \Drupal::request();
    $tab = $request->get('tab');
    if (!$tab) {
      $tab = 'values';
    }
    if (!empty($tab) && $tab == $element['#id']) {
      return;
    }
    foreach ($element as $key => $field) {
      if (is_array($field) && isset($field['#type'])) {
        hide($element[$key]);
      }
    }
  }

}
