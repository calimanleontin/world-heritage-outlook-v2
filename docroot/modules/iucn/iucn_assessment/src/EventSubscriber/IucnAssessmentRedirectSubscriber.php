<?php

namespace Drupal\iucn_assessment\EventSubscriber;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class IucnAssessmentRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return([
      KernelEvents::REQUEST => [
        ['redirectRevisions'],
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
      if (!$node->access('edit')) {
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

}
