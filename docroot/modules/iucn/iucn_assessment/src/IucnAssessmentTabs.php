<?php

namespace Drupal\iucn_assessment;

use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;

/**
 * Iucn Assessment tabs.
 */
class IucnAssessmentTabs {

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
    if ($node->bundle() == 'site_assessment' && $node->access('update')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
