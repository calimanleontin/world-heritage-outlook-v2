<?php

namespace Drupal\iucn_site\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
* Updates site geoJson file.
*
* @QueueWorker(
*   id = "cron_site_update_geojson",
*   title = @Translation("Cron Node Publisher"),
*   cron = {"time" = 60}
* )
*/
class IucnSiteWorker extends QueueWorkerBase {

  public function processItem($data) {

  }

}