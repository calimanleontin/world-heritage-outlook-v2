<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class NodeSiteAssessmentForm {

  public static function alter(&$form, FormStateInterface $form_state) {
    foreach (['status', 'revision_log', 'revision_information', 'revision'] as $item) {
      $form[$item]['#access'] = FALSE;
    }

    // Hide all revision related settings and check if a new revision should
    // be created in hook_node_presave().
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;
  }
}