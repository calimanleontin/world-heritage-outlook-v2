<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;

class IucnNodeController extends NodeController {

  public function revisionOverview(NodeInterface $node) {
    $build = parent::revisionOverview($node);
    foreach ($build['node_revisions_table']['#rows'] as &$row) {
      foreach ($row as &$column) {
        if (!empty($column['data']) && !empty($column['data']['#type']) && $column['data']['#type'] == 'operations') {
          /** @var \Drupal\Core\Url $delete_route */
          $delete_route = $column['data']['#links']['delete']['url'];
          $vid = $delete_route->getRouteParameters()['node_revision'];
          $edit_route = Url::fromRoute('node.revision_edit', ['node' => $node->id(), 'node_revision' => $vid]);
          $column['data']['#links']['edit'] = [
            'title' => $this->t('Edit'),
            'url' => $edit_route,
          ];
        }
      }
    }

    return $build;
  }

  public function stateChangeForm(NodeInterface $node) {
    $edit_form = \Drupal::entityTypeManager()->getFormObject('node', 'state_change')->setEntity($node);
    return \Drupal::formBuilder()->getForm($edit_form);
  }

}
