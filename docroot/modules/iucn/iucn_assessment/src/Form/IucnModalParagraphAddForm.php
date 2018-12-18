<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

class IucnModalParagraphAddForm extends IucnModalForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    $temporary_data = $form_state->getTemporary();
    $field = $route_match->getParameter('field');

    $parent_entity_revision = isset($temporary_data['node_revision']) ?
      $temporary_data['node_revision'] :
      $route_match->getParameter('node_revision');

    $this->entity->setParentEntity($parent_entity_revision, $field);
    $this->entity->save();

    $this->insertParagraph($parent_entity_revision, $field);

    $save_status = $parent_entity_revision->save();

    $form_state->setTemporary(['parent_entity_revision' => $parent_entity_revision]);

    return $save_status;
  }

  /**
   * Insert the value into the ItemList either before or after.
   */
  protected function insertParagraph($parent_entity, $field) {
    $paragraph = $this->entity;
    if ($paragraph->bundle() == 'as_site_reference' && $parent_entity instanceof NodeInterface) {
      $value = $paragraph->field_reference->value;
      $lines = preg_split('/\r\n|\r|\n/', $value);
      array_walk($lines, function (&$walkValue) {
        $walkValue = trim($walkValue);
      });
      $lines = array_filter($lines);

      if (count($lines) > 1) {
        // If user entered multiple references separated by EOL, we create a new
        // paragraph for each line.
        $firstValue = array_shift($lines);
        $paragraph->set('field_reference', $firstValue);
        $paragraph->save();
        $parent_entity->get($field)->appendItem([
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ]);
        foreach ($lines as $line) {
          $newParagraph = \Drupal\paragraphs\Entity\Paragraph::create([
            'type' => 'as_site_reference',
            'field_reference' => $line,
          ]);
          $newParagraph->save();
          $parent_entity->get($field)->appendItem([
            'target_id' => $newParagraph->id(),
            'target_revision_id' => $newParagraph->getRevisionId(),
          ]);
        }
        $parent_entity->save();
        return;
      }
    }

    $value = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $parent_entity->get($field)->appendItem($value);
  }

}
