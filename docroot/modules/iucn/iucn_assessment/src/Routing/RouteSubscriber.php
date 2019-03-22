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
      $route->setRequirement('_custom_access', '\Drupal\iucn_assessment\Plugin\Access\AssessmentAccess::assessmentEditAccess');
    }

    $route = $collection->get('entity.node.content_translation_overview');
    if ($route) {
      $route->setRequirement('_custom_access', '\Drupal\iucn_assessment\Plugin\Access\AssessmentAccess::translationOverviewAccess');
    }

    // Hide unnecessary workflow tab.
    $route = $collection->get('entity.node.workflow_history');
    if ($route) {
      $route->setRequirement('_access', 'FALSE');
    }

    $route = $collection->get('entity.node.version_history');
    if ($route) {
      $route->setRequirement('_role', 'administrator');
    }
  }

}
