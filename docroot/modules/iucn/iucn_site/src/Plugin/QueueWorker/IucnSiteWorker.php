<?php

namespace Drupal\iucn_site\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
* Updates site geoJson file.
*
* @QueueWorker(
*   id = "cron_site_update_geojson",
*   title = @Translation("Cron Site Update geoJson"),
*   cron = {"time" = 5}
* )
*/
class IucnSiteWorker extends QueueWorkerBase {

  public function processItem($site_nid) {
    $node = Node::load($site_nid);
    if (!empty($node)) {
      \Drupal::service('iucn_site.utils')->updateGeoJson($node);
    }
  }

}