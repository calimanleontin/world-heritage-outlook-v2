<?php

namespace Drupal\iucn_diff_revisions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\diff\DiffEntityComparison;


/**
 * Revision comparison service that prepares a diff of a pair of revisions.
 */
class DiffController extends ControllerBase {

  protected $entityTypeManager;

  protected $nodeStorage;

  protected $entityComparison;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, DiffEntityComparison $entityComparison) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->entityComparison = $entityComparison;
  }

  public function compareRevisions($vid1, $vid2) {
    /** @var \Drupal\node\NodeInterface $revision1 */
    $revision1 = $this->nodeStorage->loadRevision($vid1);
    /** @var \Drupal\node\NodeInterface $revision2 */
    $revision2 = $this->nodeStorage->loadRevision($vid2);

    $fields = $this->entityComparison->compareRevisions($revision1, $revision2);

    $diff = [];
    foreach ($fields as $key => $field) {
      $this->entityComparison->processStateLine($field);
      $field_diff_rows = $this->entityComparison->getRows(
        $field['#data']['#left'],
        $field['#data']['#right']
      );
      if (!empty($field_diff_rows)) {
        $diff[$key] = $field_diff_rows;
      }
    }
    return $diff;
  }

}