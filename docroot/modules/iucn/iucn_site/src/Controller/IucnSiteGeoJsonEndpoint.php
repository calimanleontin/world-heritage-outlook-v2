<?php

/**
 * @file
 * Get the geoJson file for an associated site.
 */

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;

class IucnSiteGeoJsonEndpoint extends ControllerBase {

  public function getGeoJson($nid) {
    $site = Node::load($nid);
    /** @var File $file */
    $file = $site->field_geojson->entity;
    if (empty($site) || $site->bundle() != 'site' || empty($file)) {
      return new Response(NULL);
    }
    $file_uri = $file->getFileUri();
    $json = utf8_encode(file_get_contents($file_uri));

    // Set cache max-age to 1 month and cache tags of the site.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheMaxAge(60 * 60 * 24 * 30);
    $cache_metadata->setCacheTags($site->getCacheTags());
    $response = new CacheableResponse($json);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }
}