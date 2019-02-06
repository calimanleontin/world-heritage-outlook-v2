<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

class IucnModalParagraphAddForm extends IucnModalParagraphForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $temporary_data = $form_state->getTemporary();

    $parent_entity_revision = isset($temporary_data['node_revision']) ?
      $temporary_data['node_revision'] :
      $this->nodeRevision;

    $this->entity->setParentEntity($parent_entity_revision, $this->fieldName);
    $this->entity->save();

    $this->insertParagraph($parent_entity_revision, $this->fieldName);

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
          $newParagraph = Paragraph::create([
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
