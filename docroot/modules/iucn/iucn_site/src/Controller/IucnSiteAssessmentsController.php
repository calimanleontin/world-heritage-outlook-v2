<?php

/**
 * @file
 * Redirect old site links to new ones.
 */

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IucnSiteAssessmentsController extends ControllerBase {

  /**
   * Access check for assessments tab.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   access.
   */
  public function access(Node $node) {
    if ($node->bundle() == 'site' && $node->access('update')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * View of site assessments.
   *
   * @param \Drupal\node\Entity\Node $node
   *   node
   *
   * @return array|null
   *   render array.
   */
  public function siteAssessments(Node $node) {
    $view = Views::getView('site_s_assessments');
    $content = [
      '#cache' => [
        'tags' => ['node:' . $node->id()],
      ],
    ];
    if (is_object($view)) {
      $view->setDisplay('block_1');
      $view->preExecute();
      $view->execute();
      $content = $view->buildRenderable('block_1');
    }
    return $content;
  }

}
