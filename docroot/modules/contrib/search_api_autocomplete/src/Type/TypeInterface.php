<?php

namespace Drupal\search_api_autocomplete\Type;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Defines the auto complete type plugin.
 *
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteType
 * @see \Drupal\search_api_autocomplete\Type\TypeManager
 */
interface TypeInterface {

  /**
   * Returns the label of the autocompletion type.
   *
   * @return string
   *   The label of the type.
   */
  public function getLabel();

  /**
   * Returns the description of the autocompletion type.
   *
   * @return string
   *   The type description.
   */
  public function getDescription();

  /**
   * Returns a list of searches for this index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   A search api index.
   *
   * @return array
   *   An array of searches.
   */
  public function listSearches(IndexInterface $index);

  /**
   * Creates a search query based on this search type.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The autocomplete search configuration.
   * @param string $keys
   *   The keywords to set on the query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The created query.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the query couldn't be created.
   */
  public function createQuery(SearchInterface $search, $keys);

}
