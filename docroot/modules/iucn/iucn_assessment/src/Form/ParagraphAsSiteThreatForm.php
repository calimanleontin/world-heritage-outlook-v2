<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class ParagraphAsSiteThreatForm {

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    $form['field_as_threats_extent']['#states'] = [
      'visible' => [
        ':input[data-drupal-selector="edit-field-as-threats-in-value"]' => ['checked' => TRUE],
      ],
    ];

  }
}