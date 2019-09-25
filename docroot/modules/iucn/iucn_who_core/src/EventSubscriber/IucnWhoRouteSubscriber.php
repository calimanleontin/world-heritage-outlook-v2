<?php

namespace Drupal\iucn_who_core\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\iucn_who_core\Form\IucnUserPasswordForm;
use Symfony\Component\Routing\RouteCollection;

class IucnWhoRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RoutingEvents::ALTER => [
        ['onAlterRoutes', -9999],
      ],
    ];
  }

  public function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('user.pass');
    if ($route) {
      $route->setDefault('_form', IucnUserPasswordForm::class);
    }

    if ($route = $collection->get('rest.csrftoken')) {
      $collection->remove('rest.csrftoken');
    }
  }

}
