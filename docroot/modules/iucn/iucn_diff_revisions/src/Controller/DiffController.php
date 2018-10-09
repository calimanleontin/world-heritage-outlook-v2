<?php

namespace Drupal\iucn_diff_revisions;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\diff\Controller\NodeRevisionController;


/**
 * Revision comparison service that prepares a diff of a pair of revisions.
 */
class DiffController extends ControllerBase {

  protected $nodeRevisionController;

  public function __construct(NodeRevisionController $nodeRevisionController) {
    $this->nodeRevisionController = $nodeRevisionController;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      NodeRevisionController::create($container)
    );
  }

  public function getDifferences(NodeInterface $revision_1, NodeInterface $revision_2) {
    // @todo
    return [];
  }

}