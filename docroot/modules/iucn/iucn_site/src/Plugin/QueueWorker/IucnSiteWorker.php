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
    $gis_url = "http://services5.arcgis.com/Mj0hjvkNtV7NRhA7/arcgis/rest/services/Latest_WH/FeatureServer/0/query?where=wdpaid%3D^SITE_ID&objectIds=&time=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=true&outFields=&returnGeometry=true&returnCentroid=true&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=4326&returnIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnDistinctValues=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&quantizationParameters=&sqlFormat=none&f=pgeojson&token=";
    $gis_url = strtr($gis_url, ['^SITE_ID' => $node->field_wdpa_id->value]);
    $geojson = file_get_contents($gis_url);
    /** @var File $file */
    $file = $node->field_geojson->entity;
    file_put_contents($file->getFileUri(), $geojson);
    \Drupal::logger('iucn_site')->notice('geoJson' . $node->field_wdpa_id->value . 'was successfully updated');
  }

}