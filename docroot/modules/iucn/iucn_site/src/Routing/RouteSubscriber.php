<?php
/**
 * @file
 * Contains \Drupal\iucn_site\Routing\RouteSubscriber.
 */

namespace Drupal\iucn_site\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -9999];  // negative Values means "late"
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection)
  {
    $route = $collection->get('entity.node.canonical');
    if ($route) {
      $route->setDefault('_controller', '\Drupal\iucn_site\Controller\IucnRoutesController::nodeRedirect');
    }
  }

}
