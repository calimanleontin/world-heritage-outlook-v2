<?php

namespace Drupal\search_api_autocomplete\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\AutocompleteFormUtility;
use Drupal\search_api_autocomplete\SearchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a controller for autocompletion.
 */
class AutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a new AutocompleteController instance.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Page callback: Retrieves autocomplete suggestions.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search_api_autocomplete_search
   *   The search for which to retrieve autocomplete suggestions.
   * @param string $fields
   *   A comma-separated list of fields on which to do autocompletion. Or "-"
   *   to use all fulltext fields.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The autocompletion response.
   */
  public function autocomplete(SearchInterface $search_api_autocomplete_search, $fields, Request $request) {
    $matches = [];
    $autocomplete_utility = new AutocompleteFormUtility($this->renderer);
    try {
      if ($search_api_autocomplete_search->supportsAutocompletion()) {
        $keys = $request->query->get('q');
        list($complete, $incomplete) = $autocomplete_utility->splitKeys($keys);
        $query = $search_api_autocomplete_search->getQuery($complete);
        if ($query) {
          // @todo Maybe make range configurable?
          $query->range(0, 10);
          $query->setOption('search id', 'search_api_autocomplete:' . $search_api_autocomplete_search->id());
          if (!empty($search_api_autocomplete_search->getOption('fields'))) {
            $query->setFulltextFields($search_api_autocomplete_search->getOption('fields'));
          }
          elseif ($fields != '-') {
            $fields = explode(' ', $fields);
            $query->setFulltextFields($fields);
          }
          $query->preExecute();
          $suggestions = $search_api_autocomplete_search->getSuggesterInstance()
            ->getAutocompleteSuggestions($query, $incomplete, $keys);
          if ($suggestions) {
            foreach ($suggestions as $suggestion) {
              if (!$search_api_autocomplete_search->getOption('show_count')) {
                $suggestion->setResults(NULL);
              }

              // Decide what the action of the suggestion is – entering specific
              // search terms or redirecting to a URL.
              if ($suggestion->getUrl()) {
                $key = ' ' . $suggestion->getUrl()->toString();
              }
              else {
                $key = trim($suggestion->getKeys());
              }

              if (!isset($ret[$key])) {
                $ret[$key] = $suggestion;
              }
            }

            $alter_params = [
              'query' => $query,
              'search' => $search_api_autocomplete_search,
              'incomplete_key' => $incomplete,
              'user_input' => $keys,
            ];
            $this->moduleHandler()->alter('search_api_autocomplete_suggestions', $ret, $alter_params);

            /*** @var \Drupal\search_api_autocomplete\SuggestionInterface $suggestion */
            foreach ($ret as $suggestion) {
              if ($build = $suggestion->toRenderable()) {
                $matches[] = [
                  // @todo Why doesn't $key work here for numeric suggestions?
                  'value' => $suggestion->getKeys(),
                  'label' => $this->renderer->render($build),
                ];
              }
            }
          }
        }
      }
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api_autocomplete', $e, '%type while retrieving autocomplete suggestions: !message in %function (line %line of %file).');
    }

    return new JsonResponse($matches);
  }

  /**
   * Checks access to the autocompletion route.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search_api_autocomplete_search
   *   The configured autocompletion search.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(SearchInterface $search_api_autocomplete_search, AccountInterface $account) {
    $permission = 'use search_api_autocomplete for ' . $search_api_autocomplete_search->id();
    $access = AccessResult::allowedIf($search_api_autocomplete_search->status())
      ->andIf(AccessResult::allowedIf($search_api_autocomplete_search->getIndexInstance()->status()))
      ->andIf(AccessResult::allowedIfHasPermission($account, $permission))
      ->andIf(AccessResult::allowedIf($search_api_autocomplete_search->supportsAutocompletion()))
      ->cachePerPermissions()
      ->addCacheableDependency($search_api_autocomplete_search);
    if ($access instanceof AccessResultReasonInterface) {
      $access->setReason("The \"$permission\" permission is required and autocomplete for this search must be enabled.");
    }
    return $access;
  }

}
