<?php

namespace Drupal\iucn_assessment\Form;

trait AssessmentEntityFormTrait {

  public static function hideUnnecessaryFields(array &$form) {
    // Hide unnecessary fields.
    unset($form['actions']['delete']);
    unset($form['advanced']);
    unset($form['revision']);
    unset($form['revision_log']);
    unset($form['author']);
    unset($form['meta']);
    if (!empty($form['field_state'])) {
      $form['field_state']['#access'] = FALSE;
    }
  }

  public static function addRedirectToAllActions(array &$form) {
    // Redirect to node edit on form submit.
    foreach ($form['actions'] as $key => &$action) {
      if (strpos($key, 'workflow_') !== FALSE || $key == 'submit') {
        $action['#submit'][] = [self::class, 'assessmentSubmitRedirect'];
      }
    }
  }

}
