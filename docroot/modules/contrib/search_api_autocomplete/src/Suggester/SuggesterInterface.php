<?php

namespace Drupal\search_api_autocomplete\Suggester;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Represents a plugin for creating autocomplete suggestions.
 *
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterManager
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSuggester
 */
interface SuggesterInterface extends ConfigurablePluginInterface, PluginInspectionInterface {

  /**
   * Determines whether this plugin class supports the given index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index in question.
   *
   * @return bool
   *   TRUE if this plugin supports the given search index, FALSE otherwise.
   */
  public static function supportsIndex(IndexInterface $index);

  /**
   * Retrieves the search this plugin is configured for.
   *
   * @return \Drupal\search_api_autocomplete\SearchInterface
   *   The search this plugin is configured for.
   */
  public function getSearch();

  /**
   * Retrieves the index associated with this plugin's search.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index with which the plugin's search is associated.
   */
  public function getIndex();

  /**
   * Retrieves the plugin's label.
   *
   * @return string
   *   The plugin's human-readable and translated label.
   */
  public function label();

  /**
   * Retrieves the plugin's description.
   *
   * @return string|null
   *   The plugin's translated description; or NULL if it has none.
   */
  public function getDescription();

  /**
   * Retrieves autocompletion suggestions for some user input.
   *
   * For example, when given the user input "teach us", with "us" being
   * considered incomplete, the following might be returned:
   *
   * @code
   *   [
   *     [
   *       'prefix' => t('Did you mean:'),
   *       'user_input' => 'reach us',
   *     ],
   *     [
   *       'user_input' => 'teach us',
   *       'suggestion_suffix' => 'ers',
   *     ],
   *     [
   *       'user_input' => 'teach us',
   *       'suggestion_suffix' => ' swimming',
   *     ],
   *     'teach users swimming',
   *   ];
   * @endcode
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input);

}
