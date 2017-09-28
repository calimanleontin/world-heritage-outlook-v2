<?php

/**
 * @file
 * Redirect old site links to new ones.
 */

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IucnSiteRedirectController extends ControllerBase {

  public function redirectSite($lang, $wdpaid) {
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', 'site');
    $query->condition('field_wdpa_id', $wdpaid);
    $site_id = $query->execute();

    if (empty($site_id)) {
      return new RedirectResponse('/');
    }

    $url = Node::load(reset($site_id))->url();
    if ($lang != 'en') {
      $url = '/' . $lang . $url;
    }
    return new RedirectResponse($url, 301);
  }

}
