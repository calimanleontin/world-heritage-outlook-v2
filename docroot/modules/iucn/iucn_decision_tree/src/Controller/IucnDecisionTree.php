<?php

/**
 * @file
 * Provide an endpoint for loading decision node
 */

namespace Drupal\iucn_decision_tree\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\node\Controller;

class IucnDecisionTree extends ControllerBase {


  public function loadNode($nid) {

    $decision = Node::load($nid);

    $render_output = [
      '#type' => 'markup',
      '#markup' => 'Node cannot be loaded.',
    ];



    if ($decision && $decision->getType() == 'decision') {
      $render_controller = \Drupal::entityTypeManager()->getViewBuilder($decision->getEntityTypeId());
      $render_output = $render_controller->view($decision, 'ajax');

      $children = $decision->get('field_decision')->referencedEntities();
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
          $render_output['#prefix'] = '<div class="decision-wrapper yes-no-decision">';
          $render_output['#suffix'] = '</div>';
        }
        elseif (isset($decisions['yes'][0])) {
          // Only yes decision is set.
          //No decisions. Assume these are final nodes.
          $render_output['#prefix'] = '<div class="decision-wrapper only-yes-decision">';
          $render_output['#suffix'] = '</div>';
        }
      }
      else {
        //No decisions. Assume these are final nodes.
        $render_output['#prefix'] = '<div class="decision-wrapper final-decision">';
        $render_output['#suffix'] = '</div>';
      }

    }
    return $render_output;
  }

  public function loadNodes($nodes) {

    $render_output = [
      '#type' => 'markup',
      '#markup' => 'Nodes cannot be loaded.',
    ];

    $node_ids = explode(',', $nodes);
    if ($node_ids) {
      $render_output = [];
      foreach ($node_ids as $nid) {
        $render_output[] = $this->loadNode($nid);
      }
    }
    return new Response(render($render_output));
  }

}
