<?php

namespace Drupal\iucn_assessment\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Negative Values means "late".
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -9999];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.node.edit_form');
    if ($route) {
      $route->setRequirement('_custom_access', 'Drupal\iucn_assessment\Plugin\Access\AssessmentAccess::assessmentEdit');
    }

    // Alter the revision page so we can add the Revision Edit button.
//    $route = $collection->get('entity.node.version_history');
//    if ($route) {
//      $route->setDefaults(array(
//        '_controller' => '\Drupal\iucn_assessment\Controller\IucnNodeController::revisionOverview',
//      ));
//    }

    // Hide unnecessary workflow tab.
    $route = $collection->get('entity.node.workflow_history');
    if ($route) {
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
