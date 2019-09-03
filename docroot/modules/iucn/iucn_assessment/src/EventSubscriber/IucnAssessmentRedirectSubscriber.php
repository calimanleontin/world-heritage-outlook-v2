<?php

namespace Drupal\iucn_assessment\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class IucnAssessmentRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $accountProxy;

  /**
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $account_proxy, LanguageManagerInterface $languageManager) {
    $this->accountProxy = $account_proxy;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return ([
      KernelEvents::REQUEST => [
        ['redirectRevisions', 27],
        ['redirectAssessmentLanguage', 30],
      ],
    ]);
  }

  /**
   * Redirects revision link of assessments to the site page where it is.
   *
   * custom handled the revision loading into iucn_assessment_preprocess_field()
   */
  public function redirectRevisions(GetResponseEvent $event) {
    $request = $event->getRequest();
    // Taxonomy term pages are forbidden for anonymous users.
    if ($request->attributes->get('_route') === 'entity.node.revision') {
      if (\Drupal::currentUser()->isAnonymous()) {
        throw new AccessDeniedHttpException();
      }

      /** @var Node $node */
      $node_id = $request->attributes->get('node');
      $node = Node::load($node_id);
      $node_revision = $request->attributes->get('node_revision');

      if ($node->bundle() !== 'site_assessment') {
        return;
      }

      if (!$node->access('edit') && !\Drupal::currentUser()->hasPermission('view site_assessment revisions')) {
        throw new AccessDeniedHttpException();
      }

      $params = [
        'year' => $node->field_as_cycle->value,
        'revision' => $node_revision,
      ];
      $path = '/node/' . $node->field_as_site->entity->id();
      $url = Url::fromUserInput($path, ['query' => $params])->toString();
      $response = new TrustedRedirectResponse($url, 301);
      $event->setResponse($response);
    }
  }

  /**
   * Redirect assessment to the english language
   */
  public function redirectAssessmentLanguage(GetResponseEvent $event) {
    $request = $event->getRequest();
    $allowedRoutes = [
      'entity.node.edit_form',
      'node.revision_edit',
      'iucn_assessment.node.state_change',
      'iucn_assessment.node_revision.state_change',
      'iucn_assessment.node.assign_users',
      'iucn_assessment.node.word_export',
    ];

    $routeName = $request->attributes->get('_route');

    if (!in_array($routeName, $allowedRoutes)) {
      return;
    }

    $language = $this->languageManager->getCurrentLanguage()->getId();
    if ($language == 'en') {
      return;
    }

    /** @var Node $node */
    $node = $request->attributes->get('node');

    if (is_numeric($node)) {
      $node = Node::load($node);
    }

    if (!$node instanceof NodeInterface) {
      return;
    }

    if ($node->bundle() !== 'site_assessment') {
      return;
    }

    $url = Url::fromRoute(
      $routeName,
      $request->attributes->get('_raw_variables')->all(),
      [
        'query' => $request->query->all(),
        'language' => $this->languageManager->getLanguage('en'),
      ])->toString();

    $request->query->remove('destination');
    $response = new TrustedRedirectResponse($url, 301);

    $event->setResponse($response);
  }
}
