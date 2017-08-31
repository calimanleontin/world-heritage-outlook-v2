<?php

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IucnRoutesController {

  /**
   * {@inheritdoc}
   */
  public function nodeRedirect(Request $request, NodeInterface $node) {
    $referer = $request->headers->get('referer');
    // Redirect publication page to external website if exists.
    // Go to publication page after node save.
    if ($node->getType() == 'publication' && strpos($referer, '/add/publication') === FALSE) {
      if (!empty($node->get('field_external_website')->getValue())) {
        return new TrustedRedirectResponse($node->get('field_external_website')->getValue()[0]['uri']);
      }
    }

    // Redirect FAQ details page to FAQ list.
    if ($node->getType() == 'faq') {
      $url = Url::fromRoute('view.frequently_asked_questions.page_1')->toString();
      return new RedirectResponse($url);
    }
    return node_view($node);
  }

}
