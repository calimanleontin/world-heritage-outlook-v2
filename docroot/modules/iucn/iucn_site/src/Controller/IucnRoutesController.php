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

  public function nodeRedirect(Request $request, NodeInterface $node) {
    if ($node->getType() == 'publication') {
      if (!empty($node->get('field_external_website')->getValue())) {
        return new TrustedRedirectResponse($node->get('field_external_website')->getValue()[0]['uri']);
      }
    }
    return node_view($node);
  }

}
