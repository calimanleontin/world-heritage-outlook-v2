<?php

/**
 * @file
 * Contains \Drupal\iucn_migrate\Controller\SitesMigrationController.
 */

namespace Drupal\iucn_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * An example controller.
 */
class SitesMigrationController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $path = drupal_get_path('module', 'iucn_migrate');
    $sites_file_content = file_get_contents($path.'/'.'source/sites.json');

    return new JsonResponse( json_decode($sites_file_content) );
  }

}
