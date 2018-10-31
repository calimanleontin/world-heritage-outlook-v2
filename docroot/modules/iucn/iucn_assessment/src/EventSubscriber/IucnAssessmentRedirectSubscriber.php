<?php

namespace Drupal\iucn_assessment\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Cache\CacheableMetadata;

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
        ['redirectRevisions', 27], ['redirectNodeEdit', 27],
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
    $request->query->remove('destination');

    /** @var \Drupal\node\Entity\Node $node */
    $node = $request->attributes->get('node');
    if (empty($node)) {
      return;
    }
    if ($node->bundle() != 'site_assessment') {
      return;
    }

    $route = $request->attributes->get('_route');
    if ($route == 'entity.node.edit_form') {
      $redirected = $this->redirectToLastTab($node, $event);
      if ($redirected) {
        return;
      }
    }

    if (in_array($route, ['entity.node.edit_form', 'iucn_assessment.node.state_change'])) {
      if ($route === 'entity.node.edit_form') {
        $route = 'node.revision_edit';
      }
      elseif ($route === 'iucn_assessment.node.state_change') {
        $route = 'iucn_assessment.node_revision.state_change';
      }
      $state = $node->field_state->value;
      // When trying to edit an under assessment node, non assessors
      // will be redirected to the state change form.
      if ($state == AssessmentWorkflow::STATUS_UNDER_ASSESSMENT
        && $node->field_assessor->target_id != $this->accountProxy->id()) {
        $this->redirectToStateChangeForm($node, $event);
      }
      elseif (in_array($state, [AssessmentWorkflow::STATUS_NEW, AssessmentWorkflow::STATUS_FINISHED_REVIEWING])) {
        $this->redirectToStateChangeForm($node, $event);
      }
      // When trying to edit an under review assessment,
      // a reviewer will be redirected to his review, while
      // the coordinator will be redirected to the state change form.
      elseif ($state == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
        $redirected = $this->redirectToReviewerRevision($node, $route, $event);
        if (!$redirected) {
          $this->redirectToStateChangeForm($node, $event);
        }
      }
      // When trying to edit a published assessment, the user will be
      // redirected to the draft revision, if one exists.
      // Otherwise, he will be redirected to the state change form.
      elseif ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
        $redirected = $this->redirectToDraftRevision($node, $route, $event);
        if (!$redirected) {
          $this->redirectToStateChangeForm($node, $event);
        }
      }
    }
    // Editing a non-default, published revision
    // will redirect to the main revision.
    elseif (in_array($route, ['node.revision_edit', 'iucn_assessment.node_revision.state_change'])) {
      /** @var \Drupal\node\Entity\Node $node_revision */
      $node_revision = $request->attributes->get('node_revision');

      /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
      $workflow_service = \Drupal::service('iucn_assessment.workflow');
      $node_revision = $workflow_service->getAssessmentRevision($node_revision);
      if (empty($node_revision)) {
        return;
      }
      $state = $node_revision->field_state->value;
      if ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
        if (!$node_revision->isDefaultRevision()) {
          $default_revision = Node::load($node->id());
          if ($default_revision->field_state->value == AssessmentWorkflow::STATUS_PUBLISHED) {
            $this->redirectToStateChangeForm($default_revision, $event);
          }
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
      $this->setUncacheableResponse($response);
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
        $this->setUncacheableResponse($response);
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
    if (in_array($route, ['iucn_assessment.node.state_change'])) {
      return;
    }
    $url = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $node->id()]);
    $response = new TrustedRedirectResponse($url->setAbsolute(TRUE)->toString(), 301);
    $this->setUncacheableResponse($response);
    $event->setResponse($response);

    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $current_state = $node->field_state->value;

    $this->showRedirectToStateChangeFormMessage($node);
  }

  /**
   * Make a response uncacheable.
   *
   * @param \Drupal\Core\Routing\TrustedRedirectResponse $response
   *   The response.
   */
  private function setUncacheableResponse(TrustedRedirectResponse $response) {
    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response->addCacheableDependency($cache_metadata);
  }

  /**
   * Show why the user was redirected to the state change form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   */
  private function showRedirectToStateChangeFormMessage(NodeInterface $node) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $current_state = $node->field_state->value;

    $message = t('The assessment is not editable in this state.');
    if ($current_state == AssessmentWorkflow::STATUS_UNDER_REVIEW) {
      $unfinished_reviews = $workflow_service->getUnfinishedReviewerRevisions($node);
      if (!empty($unfinished_reviews)) {
        $reviewers = [];
        /** @var \Drupal\node\Entity\Node $unfinished_review */
        foreach ($unfinished_reviews as $unfinished_review) {
          $uid = $unfinished_review->getRevisionUserId();
          $user = User::load($uid)->getUsername();
          $reviewers[] = $user;
        }
        $message .= ' ' . t('Please wait for the following reviewers to finish their reviews:') . ' ';
        $message .= implode(', ', $reviewers);
      }
    }
    elseif ($current_state == $workflow_service::STATUS_PUBLISHED) {
      $message .= ' ' . t('Please create a draft first.');
    }
    elseif ($current_state == $workflow_service::STATUS_UNDER_ASSESSMENT) {
      $message .= ' ' . t('Please wait for the assessment to be finished.');
    }
    \Drupal::messenger()->addWarning($message);
  }

  /**
   * Redirect an user to the last assessment tab if no tab is explicitly selected.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   *
   * @return bool
   *   True if successfully redirected.
   */
  private function redirectToLastTab(NodeInterface $node, GetResponseEvent $event) {
    $nid = $node->id();
    $request = $event->getRequest();
    $current_tab = $request->query->get('tab');
    if (empty($current_tab) && empty($request->query->get('_wrapper_format'))) {
      $tempstore = \Drupal::service('user.private_tempstore')->get('iucn_assessment');
      $last_tab = $tempstore->get("last_tab[$nid]");
      if (!empty($last_tab)) {
        $request->query->set('tab', $last_tab);
        $url = Url::fromUri($request->getUri(), ['query' => ['tab' => $last_tab]]);;
        $response = new TrustedRedirectResponse($url->toString(), 301);
        $this->setUncacheableResponse($response);
        $event->setResponse($response);
        return TRUE;
      }
    }
    return FALSE;
  }

}
