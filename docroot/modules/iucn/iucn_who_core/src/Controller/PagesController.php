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


class PagesController extends ControllerBase {


  public function dashboard() {
    return [
      '#title' => $this->t('Dashboard'),
    ];
  }
}
