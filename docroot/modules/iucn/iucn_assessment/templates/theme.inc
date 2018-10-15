<?php

/**
 * @file
 * Preprocessors for fieldgroup elements.
 */

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
