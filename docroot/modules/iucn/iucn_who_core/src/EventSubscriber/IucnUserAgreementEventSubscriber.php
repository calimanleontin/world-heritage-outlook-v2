<?php

namespace Drupal\iucn_who_core\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\iucn_who_core\Service\UserAgreementService;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * User Agreement Event Subscriber.
 */
class IucnUserAgreementEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /** @var \Drupal\Core\Routing\RouteMatchInterface */
  protected $routeMatch;

  /** @var \Drupal\Core\Session\AccountProxyInterface */
  protected $currentUser;

  /** @var \Drupal\iucn_who_core\Service\UserAgreementService  */
  protected $userAgreementService;

  public function __construct(RouteMatchInterface $routeMatch, AccountProxyInterface $currentUser, UserAgreementService $userAgreementService) {
    $this->routeMatch = $routeMatch;
    $this->currentUser = $currentUser;
    $this->userAgreementService = $userAgreementService;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkUserAgreement');
    return $events;
  }

  /**
   * Event callback to check if the user has completed registration.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   */
  public function checkUserAgreement(GetResponseEvent $event) {
    if ($this->eventValidForRedirect($event)) {
      $url = Url::fromRoute('who.user_agreement_form');
      if ($event->getRequest()->getUri() != $url->toString()) {
        $redirect = new RedirectResponse($url->toString());
        $event->setResponse($redirect);
      }
    }
  }

  /**
   * Check if event valid for redirect.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   *
   * @return bool
   *   Whether or not valid redirect.
   */
  protected function eventValidForRedirect(GetResponseEvent $event) {
    $contentTypes = $event->getRequest()->getAcceptableContentTypes();
    return $this->currentUser->isAuthenticated()
      && $event->isMasterRequest()
      && (empty($contentTypes) || in_array('text/html', $contentTypes))
      && !$this->routeIsAllowed()
      && !$this->userAgreementService->userAcceptedAgreement()
      && !$this->userAgreementService->userCanSkipAgreement();
  }

  /**
   * Check if route allowed.
   */
  protected function routeIsAllowed() {
    $route_name = $this->routeMatch->getRouteName();
    return in_array($route_name, $this->getAllowedRoutes());
  }

  /**
   * Provide allowed routes.
   */
  protected function getAllowedRoutes() {
    return [
      'who.user_agreement_form',
      'user.logout',
      'user.cancel_confirm',
      'entity.user.cancel_form',
      'contextual.render',
      'entity.user.edit_form',
    ];
  }

}
