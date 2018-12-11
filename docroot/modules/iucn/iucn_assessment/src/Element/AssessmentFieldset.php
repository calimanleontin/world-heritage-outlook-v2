<?php

namespace Drupal\iucn_assessment\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element for a group of form elements.
 *
 * Usage example:
 * @code
 * $form['author'] = array(
 *   '#type' => 'assessment_fieldset',
 *   '#title' => $this->t('Author'),
 * );
 *
 * $form['author']['name'] = array(
 *   '#type' => 'textfield',
 *   '#title' => $this->t('Name'),
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Fieldgroup
 * @see \Drupal\Core\Render\Element\Details
 *
 * @RenderElement("assessment_fieldset")
 */
class AssessmentFieldset extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#process' => [
        [$class, 'processGroup'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#value' => NULL,
      '#theme_wrappers' => ['assessment_fieldset'],
    ];
  }

}
