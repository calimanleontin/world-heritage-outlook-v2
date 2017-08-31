<?php

/**
 * @file
 * Redirect old site links to new ones.
 */

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IucnSiteRedirectController extends ControllerBase {

  public function redirectSite($lang, $wdpaid) {
    $url = '/sites/wdpaid/' . $wdpaid;
    if ($lang != 'en') {
      $url = '/' . $lang . $url;
    }
    return new RedirectResponse($url);
  }
}