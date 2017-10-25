<?php

namespace Drupal\iucn_who_core\EventSubscriber;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class IucnWhoEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return([
      KernelEvents::REQUEST => [
        ['customizeRequest'],
      ],
    ]);
  }

  /**
   * Redirect requests for nodes, for taxonomy terms, sets globals for assess.
   */
  public function customizeRequest(GetResponseEvent $event) {
    global $_iucn_assessment_display_year;
    global $_iucn_assessment_is_latest_assessment;

    $request = $event->getRequest();

    // Taxonomy term pages are forbidden for anonymous users.
    if ($request->attributes->get('_route') === 'entity.taxonomy_term.canonical') {
      if (\Drupal::currentUser()->isAnonymous()) {
        throw new AccessDeniedHttpException();
      }
    }

    // Set globals for assessment.
    /** @var Node $node */
    $node = $request->attributes->get('node');

    if (in_array($request->attributes->get('_route'), ['iucn_pdf.download.debug', 'iucn_pdf.download'])) {
      $node = Node::load($request->attributes->get('entity_id'));
    }

    if (!empty($node) && $node->bundle() === 'site' && !empty($node->field_assessments)) {
      $iucn_config = \Drupal::config('iucn_who.settings');
      $config_year = $iucn_config->get('assessment_year');
      $_iucn_assessment_display_year = iucn_pdf_assessment_year_display($node);
      $_iucn_assessment_is_latest_assessment = $_iucn_assessment_display_year == $config_year;
    }

    // This is necessary because this also gets called on
    // node sub-tabs such as "edit", "revisions", etc.  This
    // prevents those pages from redirected.
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    // Redirect faq details page to faq list.
    if ($node->getType() === 'faq') {
      $url = Url::fromRoute('view.frequently_asked_questions.page_1')->toString();
      $response = new TrustedRedirectResponse($url, 301);
      $event->setResponse($response);
    }

    // Redirect assessment details page to site.
    if ($node->getType() === 'site_assessment') {
      $site = $node->field_as_site->entity;
      if (!empty($site)) {
        $url = $site->url();
      }
      else {
        $url = Url::fromRoute('view.sites_search.sites_search_page_database')->toString();
      }
      $response = new TrustedRedirectResponse($url, 301);
      $event->setResponse($response);
    }

    // Redirect publication page to external website if exists.
    if ($node->getType() === 'publication') {
      $referer = $request->headers->get('referer');
      // Go to publication page after node save.
      if (strpos($referer, '/add/publication') === FALSE) {
        if (!empty($node->get('field_external_website')->getValue())) {
          $response = new TrustedRedirectResponse($node->get('field_external_website')->getValue()[0]['uri'], 301);
          $event->setResponse($response);
        }
      }
    }
  }

}
