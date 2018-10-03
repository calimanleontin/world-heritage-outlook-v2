<?php

namespace Drupal\iucn_diff_revisions;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\diff\Controller\NodeRevisionController;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;


/**
 * Revision comparison service that prepares a diff of a pair of revisions.
 */
class DiffRevisionComparison {

  //  /**
  //   * An instance of the NodeRevisionController of the diff module.
  //   *
  //   * @var \Drupal\diff\Controller\NodeRevisionController
  //   */
  //  protected $nodeRevisionController;
  //
  //  /**
  //   * Constructs a DiffEntityComparison object.
  //   *
  //   */
  //  public function __construct($node_revision_controller)
  //  {
  //    $this->nodeRevisionController = $node_revision_controller;
  //  }
  //
  //  /**
  //   * {@inheritdoc}
  //   */
  //  public static function create(ContainerInterface $container) {
  //    return new static(
  //      new NodeRevisionController
  //    );
  //  }


  /**
   * Updates the diff information field on the node's revision.
   *
   * @param Node
   *  The Node's id.
   */
  public function updateRevisionsDiff(Node $node) {
    $node_revision_controller = NodeRevisionController::create(\Drupal::getContainer());
    $left_revision = $node->id();
    $right_revision = $node->getLoadedRevisionId();
    $results = $node_revision_controller->compareNodeRevisions($node, $left_revision, $right_revision, NULL);
    return $results;
  }

}