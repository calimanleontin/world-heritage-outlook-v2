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

  public function loadNodes($nodes, $level) {
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

  /** ROUTE:
   * iucn_decision_tree.decision_tree:
  *  path: 'benefits/decision-tree/{node_title}/{param1}/{param2}/{param3}/{param4}/{param5}'
  *  defaults:
  *  _controller: '\Drupal\iucn_decision_tree\Controller\IucnDecisionTree::loadDecisionTree'
  *  param2 : NULL
  *  param3 : NULL
  *  param4 : NULL
  *  param5 : NULL
  * requirements:
  *  _permission: 'access content'
  *
  * */

  public function loadDecisionTree($node_title, $param1, $param2 = NULL, $param3 = NULL, $param4 = NULL, $param5  = NULL){
    $render_output = [
      '#type' => 'markup',
      '#markup' => $node_title,
    ];
    $param = 'param';

    for ($i = 1; $i<=5; $i++) {
      $p = "param$i";
        $render_output['#markup'] .= ' - ' . $$p;
    }

    return new Response(render($render_output));
  }

}
