<?php

namespace Drupal\iucn_site\Plugin;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

class IucnSiteUtils {

  /**
   * Creates a new json file and appends it to a site's field_geojson.
   */
  public function createGeoJson(Node $node) {
    if ($node->bundle() != 'site') {
      return;
    }
    $file = $node->field_geojson->entity;
    // Create a new File if field_geojson is empty.
    if (empty($file)) {
      $filename = 'geojson' . $node->field_wdpa_id->value . '.json';
      $file = File::create([
        'filename' => $filename,
        'status' => 1,
        'uri' => 'public://geojson/' . $filename,
      ]);
      $file->setPermanent();
      $file->save();
      $node->field_geojson->entity = $file->id();
      $file_usage = \Drupal::service('file.usage');
      $file_usage->add($file, 'iucn_site', 'node', $node->id());
    }

    // Create the directory if one doesn't exist already.
    $dirname = dirname($file->getFileUri());
    file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);

    // Create the file if one doesn't exist already.
    if (!file_exists($file->getFileUri())) {
      file_put_contents($file->getFileUri(), "{}");
    }
    $file->setSize(filesize($file->getFileUri()));
    $file->save();
  }

  /**
   * Updates the geojson file of a site.
   */
  public function updateGeoJson(Node $node) {
    $gis_url = "http://services5.arcgis.com/Mj0hjvkNtV7NRhA7/arcgis/rest/services/Latest_WH/FeatureServer/0/query?where=wdpaid%3D^SITE_ID&objectIds=&time=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=true&outFields=&returnGeometry=true&returnCentroid=true&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=4326&returnIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnDistinctValues=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&quantizationParameters=&sqlFormat=none&f=pgeojson&token=";
    $gis_url = strtr($gis_url, ['^SITE_ID' => $node->field_wdpa_id->value]);
    $geojson = file_get_contents($gis_url);
    /** @var File $file */
    $file = $node->field_geojson->entity;
    if (empty($file) || $geojson == FALSE) {
      return;
    }
    file_put_contents($file->getFileUri(), $geojson);
    $file->setSize(filesize($file->getFileUri()));
    $file->save();
    \Drupal::logger('iucn_site')->notice('geoJson' . $node->field_wdpa_id->value . 'was successfully updated');
  }

  /**
   * Get site_assessment for the current year
   *
   * @param \Drupal\node\Entity\Node $node
   *
   * @return \Drupal\node\Entity\Node|null
   */
  public static function getMainSiteAssessment(Node $node) {
    if ($node->bundle() != 'site') {
      return NULL;
    }

    $displayYear = iucn_pdf_assessment_year_display($node);
    /* @var \Drupal\node\Entity\Node $assessment */
    foreach ($node->get('field_assessments')->referencedEntities() as $assessment) {
      if ($assessment->field_as_cycle->value == $displayYear
        && $assessment->access('view')) {
        return $assessment;
      }
    }

    return NULL;
  }

}
