<?php

namespace Drupal\iucn_decision_tree\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;
use Drupal\node\Entity\Node;

/**
 * Plugin that renders Rating image.
 *
 * @DsField(
 *   id = "decision_tree_holder",
 *   title = @Translation("Decision tree holder"),
 *   entity_type = "node",
 *   provider = "node"
 * )
 */
class DecisionTreeHolder extends DsFieldBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $decisions = [];
    $rendered_content = NULL;
    $children = $this->entity()->get('field_decision')->referencedEntities();
    if ($children) {
      foreach ($children as $child) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $child */
        $decision_target = $child->get('field_decision')->getValue();
        $decision_nid = $decision_target[0]['target_id'];
        $decision_relation = $child->get('field_relation')->value;
        $decisions[$decision_relation][] = $decision_nid;
      }
    }
    if ($decisions) {
      // Decisions are set.
      if (isset($decisions['yes']) && isset($decisions['no'])) {
        // Yes and No decisions are set.
      }
      else {
        $decision = Node::load($decisions['yes'][0]);
        $render_controller = \Drupal::entityTypeManager()->getViewBuilder($decision->getEntityTypeId());
        $element['#value'] = render($render_controller->view($decision, 'ajax'));
        $rendered_content = $element['#value'];
      }
    }
    $request = \Drupal::request();
    $level = $request->get('level');
    $level++;

    return [
      '#theme' => 'decision_tree',
      '#entity_id' => $this->entity()->id(),
      '#decisions' => $decisions,
      '#rendered_content' => $rendered_content,
      '#level' => $level,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    if ($this->bundle() != 'decision') {
      return FALSE;
    }
    return parent::isAllowed();

  }

}
