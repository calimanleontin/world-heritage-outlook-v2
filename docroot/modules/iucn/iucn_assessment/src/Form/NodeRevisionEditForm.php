<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;

class NodeRevisionEditForm extends ContentEntityForm {

  public function getFormId() {
    return 'revision_edit_form';
  }

//  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL, $node_revision = NULL) {
//    $revision = \Drupal::entityTypeManager()
//      ->getStorage('node')
//      ->loadRevision($node_revision);
//
//    $this->setEntity($node_revision);
//    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
//    $form_object = $form_state->getFormObject();
//    $form_object->setEntity($revision);
//
//    parent::buildForm($form, $form_state);
//  }

}