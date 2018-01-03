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
    }
    return $render_output;
  }

  public function loadNodes($nodes) {
    $render_output =
    $node_ids = explode(',', $nodes);
    if ($node_ids) {
      foreach ($node_ids as $nid) {
        $render_output[] = $this->loadNode($nid);
      }
    }
    else {
      $render_output = [
        '#type' => 'markup',
        '#markup' => 'Nodes cannot be loaded.',
      ];
    }
    return new Response(render($render_output));
  }

}