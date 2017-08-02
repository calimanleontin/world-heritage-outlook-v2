<?php

/**
 * @file
 * Provide an endpoint where a search by site name can be performed
 */

namespace Drupal\iucn_who_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


class SiteSearchController extends ControllerBase {


  /**
   * Search a site by name.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\iucn_who_core\Controller\JsonResponse
   */
  public function searchByName(Request $request) {
    $matches = array();
    $string = $request->query->get('q');
    if ($string) {
      $results = SitesQueryUtil::searchSiteByName($string);
      foreach ($results as $node) {
        $label = $node->title->value;
        $matches[] = ['value' => $label, 'label' => $label];
      }
    }
    return new JsonResponse($matches);
  }
}
