<?php

namespace Drupal\iucn_who_core\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects users when access is denied.
 *
 * Users are taken to 404 page when attempting to access the
 * user profile pages and do not have access to.
 */
class IucnAccessDeniedRedirectSubscriber implements EventSubscriberInterface {

  /**
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * AccessDeniedRedirectSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   */
  public function __construct(RouteMatchInterface $routeMatch) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * Redirects users when access is denied.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The event to process.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    if (!$exception instanceof AccessDeniedHttpException) {
      return;
    }

    if (!$this->isRouteAllowed()) {
      return;
    }

    $event->setException(new NotFoundHttpException());
  }

  public function isRouteAllowed() {
    $allowedRoutes = [
      'entity.user.canonical',
    ];

    return in_array($this->routeMatch->getRouteName(), $allowedRoutes);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 76];
    return $events;
  }
}
