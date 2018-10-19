<?php

namespace Drupal\iucn_assessment\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
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
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $account_proxy) {
    $this->accountProxy = $account_proxy;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return([
      KernelEvents::REQUEST => [
        ['redirectRevisions'], ['redirectNodeEdit'],
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

  /**
   * Redirect reviewers from node edit page to the edit page of their revision.
   */
  public function redirectNodeEdit(GetResponseEvent $event) {
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if (in_array($route, ['entity.node.edit_form', 'iucn_assessment.node.state_change'])) {
      if ($route === 'entity.node.edit_form') {
        $route = 'node.revision_edit';
      }
      elseif ($route === 'iucn_assessment.node.state_change') {
        $route = 'iucn_assessment.node_revision.state_change';
      }
      /** @var \Drupal\node\Entity\Node $node */
      $node = $request->attributes->get('node');
      if ($node->bundle() != 'site_assessment') {
        return;
      }
      $state = $node->field_state->value;
      if ($state == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
        $redirected = $this->redirectToReviewerRevision($node, $route, $event);
        if (!$redirected) {
          $this->redirectToStateChangeForm($node, $event);
        }
      }
      elseif ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
        $redirected = $this->redirectToDraftRevision($node, $route, $event);
        if (!$redirected) {
          $this->redirectToStateChangeForm($node, $event);
        }
      }
    }
  }

  /**
   * Redirect to the draft revision if it exists.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param string $route
   *   The route: node_edit or state_change form.
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   *
   * @return bool
   *   Whether or not the redirect happens.
   */
  private function redirectToDraftRevision(NodeInterface $node, $route, GetResponseEvent $event) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $draft_revision = $workflow_service->getRevisionByState($node, AssessmentWorkflow::STATUS_DRAFT);
    if (!empty($draft_revision)) {
      $url = Url::fromRoute($route, ['node' => $node->id(), 'node_revision' => $draft_revision->vid->value]);
      $response = new TrustedRedirectResponse($url->setAbsolute(TRUE)->toString(), 301);
      $event->setResponse($response);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Redirect a reviewer to his revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param string $route
   *   The route: node_edit or state_change form.
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   *
   * @return bool
   *   Whether or not the redirect happens.
   */
  private function redirectToReviewerRevision(NodeInterface $node, $route, GetResponseEvent $event) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');

    $current_user = $this->accountProxy;
    $reviewers = $workflow_service->getReviewersArray($node);
    if (empty($reviewers)) {
      return FALSE;
    }
    if (in_array($current_user->id(), $reviewers)) {
      $revision = $workflow_service->getReviewerRevision($node, $current_user->id());
      if (!empty($revision)) {
        $url = Url::fromRoute($route, ['node' => $node->id(), 'node_revision' => $revision->vid->value]);
        $response = new TrustedRedirectResponse($url->setAbsolute(TRUE)->toString(), 301);
        $event->setResponse($response);
        return TRUE;
      }
      else {
        throw new AccessDeniedHttpException();
      }
    }

    return FALSE;
  }

  /**
   * Redirect an user to the state change form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   */
  private function redirectToStateChangeForm(NodeInterface $node, GetResponseEvent $event) {
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if (in_array($route, ['iucn_assessment.node.state_change', 'iucn_assessment.node_revision.state_change'])) {
      return;
    }
    $url = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $node->id()]);
    $response = new TrustedRedirectResponse($url->setAbsolute(TRUE)->toString(), 301);
    $event->setResponse($response);
  }

}
