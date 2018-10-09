<?php

namespace Drupal\iucn_diff_revisions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\node\NodeInterface;


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
    $revision1 = $this->nodeStorage->loadRevision($vid1);
    $revision2 = $this->nodeStorage->loadRevision($vid2);

    if (!$revision1 instanceof NodeInterface || !$revision2 instanceof NodeInterface) {
      throw new \InvalidArgumentException('Invalid revisions ids.');
    }
    if ($revision1->id() != $revision2->id()) {
      throw new \InvalidArgumentException('Can only compare 2 revisions of same node.');
    }

    $fields = $this->entityComparison->compareRevisions($revision1, $revision2);

    $diff = [];
    foreach ($fields as $key => $field) {
      if (preg_match('/(\d+)\:(.+)\.(.+)/', $key, $matches)) {
        $this->entityComparison->processStateLine($field);
        $field_diff_rows = $this->entityComparison->getRows(
          $field['#data']['#left'],
          $field['#data']['#right']
        );
        if (!empty($field_diff_rows)) {
          $entityId = $matches[1];
          $entityType = $matches[2];
          $fieldName = $matches[3];
          if (empty($diff[$entityId])) {
            $diff[$entityId] = [
              'entity_id' => $entityId,
              'entity_type' => $entityType,
              'diff' => [],
            ];
          }
          $diff[$entityId]['diff'][$fieldName][] = $field_diff_rows;
        }
      }
      else {
        $this->getLogger('iucn_diff_revisions')->error('Invalid field diff key.');
      }
    }
    return $diff;
  }

}