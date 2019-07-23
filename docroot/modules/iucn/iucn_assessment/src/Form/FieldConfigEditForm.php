<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowState;

class FieldConfigEditForm {

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\field\FieldConfigInterface $fieldConfig */
    $fieldConfig = $form_state->getFormObject()->getEntity();
    if ($fieldConfig->getTargetEntityTypeId() != 'node' || $fieldConfig->getTargetBundle() != 'site_assessment') {
      return;
    }

    /** @var \Drupal\workflow\Entity\WorkflowState[] $states */
    $states = \Drupal::entityTypeManager()->getStorage('workflow_state')->loadByProperties(['wid' => 'assessment']);
    uasort($states, function (WorkflowState $a, WorkflowState $b){
      return ($a->getWeight() < $b->getWeight()) ? -1 : 1;
    });

    $form['third_party_settings']['iucn_assessment']['editable_workflow_states'] = array(
      '#type' => 'checkboxes',
      '#title' => t('(Assesment workflow) This field can be edited in following states'),
      '#default_value' => $fieldConfig->getThirdPartySetting('iucn_assessment', 'editable_workflow_states', []),
      '#options' => [],
    );

    foreach ($states as $stateId => $state) {
      $form['third_party_settings']['iucn_assessment']['editable_workflow_states']['#options'][$stateId] = $state->label();
    }
  }
}
