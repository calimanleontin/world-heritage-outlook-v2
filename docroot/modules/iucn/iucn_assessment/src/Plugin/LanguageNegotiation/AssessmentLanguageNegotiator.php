<?php

namespace Drupal\iucn_assessment\Plugin\LanguageNegotiation;

use Drupal\Core\Url;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Custom class for identifying language for assessment nodes.
 *
 * @LanguageNegotiation(
 *   id = Drupal\iucn_assessment\Plugin\LanguageNegotiation\AssessmentLanguageNegotiator::METHOD_ID,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE},
 *   weight = -99,
 *   name = @Translation("Assessment Language Switching"),
 *   description = @Translation("Language based on node translations."),
 * )
 */
class AssessmentLanguageNegotiator extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-assessment';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    if ($request->isXmlHttpRequest()) {
      return FALSE;
    }

    $alias = \Drupal::service('path.alias_manager')->getPathByAlias($request->getRequestUri());

    $url = Url::fromUri("internal:" . $alias);

    if (!$url->isRouted()) {
      return FALSE;
    }

    $params = Url::fromUri("internal:" . $alias)->getRouteParameters();
    if (empty($params['node'])) {
      return FALSE;
    }

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $node = $nodeStorage->load($params['node']);

    $types = [
      'site',
      'site_assessment',
    ];

    if (!$node instanceof NodeInterface || !in_array($node->bundle(), $types)) {
      return FALSE;
    }

    /** @var \Drupal\language\LanguageNegotiator $languageNegotiator */
    $languageNegotiator = \Drupal::service('language_negotiator');

    $langcodeFromUrl = $languageNegotiator->getNegotiationMethodInstance(LanguageNegotiationUrl::METHOD_ID)->getLangcode($request);

    if ($node->hasTranslation($langcodeFromUrl)) {
      return FALSE;
    }

    return $node->get('langcode')->value;
  }

}
