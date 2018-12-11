<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalParagraphAddForm extends IucnModalForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    $temporary_data = $form_state->getTemporary();
    $field = $route_match->getParameter('field');

    $node_revision = isset($temporary_data['node_revision']) ?
      $temporary_data['node_revision'] :
      $route_match->getParameter('node_revision');

    $parent_entity_revision = $this->entityTypeManager
      ->getStorage('node')
      ->loadRevision($node_revision);

    $this->entity->setParentEntity($parent_entity_revision, $field);
    $this->entity->save();

    $this->insertParagraph($parent_entity_revision, $field);

    $save_status = $parent_entity_revision->save();

    $form_state->setTemporary(['parent_entity_revision' => $parent_entity_revision->getRevisionId()]);

    return $save_status;
  }

  /**
   * Insert the value into the ItemList either before or after.
   */
  protected function insertParagraph($parent_entity, $field) {
    $route_match = $this->getRouteMatch();
    $value = [
      'target_id' => $this->entity->id(),
      'target_revision_id' => $this->entity->getRevisionId(),
    ];
    $parent_entity->get($field)->appendItem($value);
  }

}
