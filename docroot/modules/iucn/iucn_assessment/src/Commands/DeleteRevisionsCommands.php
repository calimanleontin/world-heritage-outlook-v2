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
*   drush_invoke_process('@self','iucn:dr');
* }
*
*/
class DeleteRevisionsCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DrushCommand.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
   public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }


  /**
   * @command iucn_assessment:delete-revisions
   * @validate-module-enabled iucn_assessment
   * @aliases iucn_assessment:delete-revisions, iucn:dr
   */
  public function deleteRevisions()
  {
    // Query with entity_type.manager.
    $query = $this->entityTypeManager->getStorage('node');
    $nodes = $query->getQuery()
      ->condition('type', 'site_assessment')
      ->execute();
    if ($nodes) {
      foreach ($nodes as $nid) {
        //Process.
        $node_storage = $this->entityTypeManager->getStorage('node');
        $node = $node_storage->load($nid);
        $this->output->writeln("Processing: $nid");
        $default_revision = $node->get('vid')->value;
        if (!$default_revision) {
          $this->logger->error("Could not find the default revision.");
          continue;
        }
        $vids = $node_storage->revisionIds($node);
        if ($vids && count($vids) > 1) {
          foreach($vids as $vid) {
            if($vid != $default_revision){
              $this->output->writeln("Deleting revision: $vid");
              $node_storage->deleteRevision($vid);
            }
          }
        }
          else {
            $this->output->writeln("No revisions do delete. Skipping.");
        }
        $this->logger->success("Done.");
        $this->output->writeln("");
        }
    }

  }

}
