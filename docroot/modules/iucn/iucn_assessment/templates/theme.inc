<?php

/**
 * @file
 * Preprocessors for fieldgroup elements.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

/**
 * Prepares variables for horizontal tabs templates.
 *
 * Default template: assessment-horizontal-tabs.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: An associative array containing the properties and children of
 *     the details element. Properties used: #children.
 *
 */
function template_preprocess_assessment_horizontal_tabs(&$variables) {
  $element = $variables['element'];
  $variables['children'] = (!empty($element['#children'])) ? $element['#children'] : '';
}

/**
 *  Prepares variables for topic tooltip element.
 * @param $variables
 */
function template_preprocess_topic_tooltip(&$variables) {
  $module_handler = \Drupal::service('module_handler');
  $module_path = $module_handler->getModule('iucn_assessment')->getPath();
  $variables['base_path'] = base_path();
  $variables['module_path'] = $module_path;
}

/**
 * Prepares variables for fieldset element templates.
 *
 * Default template: fieldset.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: An associative array containing the properties of the element.
 *     Properties used: #attributes, #children, #description, #id, #title,
 *     #value.
 */
function template_preprocess_assessment_fieldset(&$variables) {
  $element = $variables['element'];
  Element::setAttributes($element, ['id']);
  RenderElement::setAttributes($element);
  $variables['attributes'] = isset($element['#attributes']) ? $element['#attributes'] : [];
  $variables['fields_titles'] = isset($element['#fields_titles']) ? explode('|', $element['#fields_titles']) : [];
  $variables['prefix'] = isset($element['#field_prefix']) ? $element['#field_prefix'] : NULL;
  $variables['suffix'] = isset($element['#field_suffix']) ? $element['#field_suffix'] : NULL;
  $variables['title_display'] = isset($element['#title_display']) ? $element['#title_display'] : NULL;
  $variables['children'] = $element['#children'];
  $variables['required'] = !empty($element['#required']) ? $element['#required'] : NULL;

  if (isset($element['#title']) && $element['#title'] !== '') {
    $variables['legend']['title'] = ['#markup' => $element['#title']];
  }

  $variables['legend']['attributes'] = new Attribute();
  // Add 'visually-hidden' class to legend span.
  if ($variables['title_display'] == 'invisible') {
    $variables['legend_span']['attributes'] = new Attribute(['class' => ['visually-hidden']]);
  }
  else {
    $variables['legend_span']['attributes'] = new Attribute();
  }

  if (!empty($element['#description'])) {
    $description_id = $element['#attributes']['id'] . '--description';
    $description_attributes['id'] = $description_id;
    $variables['description']['attributes'] = new Attribute($description_attributes);
    $variables['description']['content'] = $element['#description'];

    // Add the description's id to the fieldset aria attributes.
    $variables['attributes']['aria-describedby'] = $description_id;
  }

  // Suppress error messages.
  $variables['errors'] = NULL;
}

function template_preprocess_rating_image_switcher(&$variables) {
  $element = $variables['element'];
  $variables['images'] = $element['#images'];
  $variables['years'] = $element['#years'];

  $iucn_config = \Drupal::config('iucn_who.settings');
  $year = $iucn_config->get('assessment_year');
  $variables['active_year'] = $year;

}

function template_preprocess_field_group_html_element_list(&$variables) {

  $element = $variables['element'];

  if (!empty($element['#title']) && !empty($element['#title_element'])) {
    $variables['title_element'] = $element['#title_element'];
    $variables['title'] = $element['#title'];
  }

  $variables['wrapper_element'] = $element['#wrapper_element'];
  $variables['attributes'] = $element['#attributes'];
  $variables['list'] = $element['list'];

}
