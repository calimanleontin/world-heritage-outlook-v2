<?php

namespace Drupal\search_api_autocomplete;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Describes the autocomplete settings for a certain search.
 */
interface SearchInterface extends ConfigEntityInterface {

  /**
   * Determines whether autocompletion is currently supported for this search.
   *
   * @return bool
   *   TRUE if autocompletion is possible for this search with the current
   *   settings; FALSE otherwise.
   */
  public function supportsAutocompletion();

  /**
   * Creates a query object for this search.
   *
   * @param string $keys
   *   The search keys.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The query that would normally be executed when $keys is entered as the
   *   keywords for this search.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the query couldn't be created.
   */
  public function getQuery($keys);

  /**
   * Retrieves the ID of the suggester plugin for this search.
   *
   * @return string
   *   This search's suggester plugin's ID.
   */
  public function getSuggesterId();

  /**
   * Sets the suggester ID.
   *
   * @param string $suggester_id
   *   The suggester plugin ID.
   *
   * @return $this
   */
  public function setSuggesterId($suggester_id);

  /**
   * Retrieves the suggester plugin for this search.
   *
   * @param bool $reset
   *   (optional) If TRUE, clear the internal static cache and reload the
   *   suggester.
   *
   * @return \Drupal\search_api_autocomplete\Suggester\SuggesterInterface|null
   *   This search's suggester plugin, or NULL if it could not be loaded.
   */
  public function getSuggesterInstance($reset = FALSE);

  /**
   * Sets the label for this search.
   *
   * @param string $label
   *   The label for the autocomplete.
   */
  public function setLabel($label);

  /**
   * Retrieves the ID of the index this search belongs to.
   *
   * @return string
   *   The index ID.
   */
  public function getIndexId();

  /**
   * Sets the ID of the index this search belongs to.
   *
   * @param string $index_id
   *   The index ID.
   *
   * @return $this
   */
  public function setIndexId($index_id);

  /**
   * Retrieves the index this search belongs to.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index this search belongs to.
   */
  public function getIndexInstance();

  /**
   * Gets the autocompletion type.
   *
   * @return string
   *   The autocompletion type.
   */
  public function getType();

  /**
   * Sets the autocompletion type.
   *
   * @param string $type
   *   The autocompletion type.
   *
   * @return $this
   */
  public function setType($type);

  /**
   * Gets the options.
   *
   * @return array
   *   The options.
   */
  public function getOptions();

  /**
   * Sets the search options.
   *
   * @param array $options
   *   The options.
   *
   * @return $this
   */
  public function setOptions(array $options);

  /**
   * Gets a specific option.
   *
   * @param string $key
   *   The key of the option.
   * @param mixed|null $default
   *   (optional) The default value.
   *
   * @return mixed|null
   *   A specific option's value.
   */
  public function getOption($key, $default = NULL);

}
