<?php

namespace Drupal\iucn_who_core\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
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

  /** @var \Drupal\Core\Config\ConfigFactoryInterface */
  protected $config;

  /** @var \Drupal\Core\Messenger\MessengerInterface */
  protected $messenger;

  /**
   * Initialize method.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(RouteMatchInterface $routeMatch, AccountProxyInterface $currentUser, ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    $this->routeMatch = $routeMatch;
    $this->currentUser = $currentUser;
    $this->config = $config_factory->get('user_agreement.settings');
    $this->messenger = $messenger;
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
        $this->messenger->addStatus($this->t('You need to accept the Terms and Conditions before using the application.'));
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
      && in_array('text/html', $contentTypes)
      && !$this->routeIsAllowed()
      && !$this->userAcceptedAgreement();
  }

  /**
   * Check if route allowed.
   */
  protected function routeIsAllowed() {
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name == 'entity.node.canonical') {
      $node = $this->routeMatch->getParameter('node');
      $nid = $this->config->get('user_agreement_node');
      if ($node instanceof NodeInterface && $node->id() == $nid) {
        return TRUE;
      }
    }
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
    ];
  }

  /**
   * Check if user accepted agreement.
   */
  protected function userAcceptedAgreement() {
    $uid = $this->currentUser->id();
    $user = User::load($uid);
    return $user->field_accepted_agreement->value || $user->hasRole('administrator');
  }

}
