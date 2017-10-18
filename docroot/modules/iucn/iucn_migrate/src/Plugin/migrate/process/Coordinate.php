<?php

/**
 * @file
 * Contains \Drupal\iucn_migrate\Plugin\migrate\process\InscriptionYear.
 */

namespace Drupal\iucn_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "iucn_coordinate",
 * )
 */
class Coordinate extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $url = "http://services5.arcgis.com/Mj0hjvkNtV7NRhA7/arcgis/rest/services/Latest_WH/FeatureServer/0/query?where=wdpaid%3D" . $value . "&objectIds=&time=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=true&outFields=&returnGeometry=true&returnCentroid=true&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=4326&returnIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnDistinctValues=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&quantizationParameters=&sqlFormat=none&f=pjson&token=";

    $data = json_decode(file_get_contents($url));
    if ($data && isset($data->features[0]->centroid->x) && isset($data->features[0]->centroid->y)) {
      return 'POINT (' . $data->features[0]->centroid->x . ' ' . $data->features[0]->centroid->y . ')';
    }
    return FALSE;
  }
}
