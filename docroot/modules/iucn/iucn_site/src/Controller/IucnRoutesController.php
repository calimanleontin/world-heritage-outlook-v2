<?php

namespace Drupal\iucn_site\Controller;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Routing\TrustedRedirectResponse;

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
    return node_view($node);
  }

}
