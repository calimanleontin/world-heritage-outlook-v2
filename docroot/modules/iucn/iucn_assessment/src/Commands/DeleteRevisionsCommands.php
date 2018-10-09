<?php

namespace Drupal\iucn_assessment\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 *
 * In addition to a commandfile like this one, you need a drush.services.yml
 * in root of your module.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 *
 * Add this command to be executed when running database updates.
 * function iucn_assessment_update_8xxx() {
 *   drush_invoke_process('@self','iucn_assessment:delete-revisions');
 * }
 *
 */
class DeleteRevisionsCommands extends DrushCommands {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /**
   * DeleteRevisionsCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Delete all non-default revisions for site assessments.
   *
   * @command iucn_assessment:delete-revisions
   *
   * @throws \Exception
   */
  public function deleteRevisions() {
    $nodes = $this->nodeStorage->getQuery()
      ->condition('type', 'site_assessment')
      ->execute();
    if (!empty($nodes)) {
      foreach ($nodes as $nid) {
        $this->logger->notice("Processing node {$nid}");

        /** @var \Drupal\node\NodeInterface $node */
        $node = $this->nodeStorage->load($nid);

        if ($node->isDefaultRevision() === FALSE) {
          throw new \Exception("Node::load didn't load the default revision for node {$nid}");
        }

        $defaultVid = $node->getRevisionId();
        $vids = $this->nodeStorage->revisionIds($node);
        foreach ($vids as $vid) {
          if ($vid != $defaultVid) {
            $this->logger->info("Deleting revision vid={$vid} for node nid={$nid}");
            $this->nodeStorage->deleteRevision($vid);
          }
        }
      }
    }
  }

}
