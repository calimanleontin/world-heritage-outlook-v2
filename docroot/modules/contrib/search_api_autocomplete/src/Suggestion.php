<?php

namespace Drupal\search_api_autocomplete;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;

/**
 * Provides a value object meant to be used as result of suggestions.
 */
class Suggestion implements SuggestionInterface {

  /**
   * The keywords this suggestion will autocomplete to.
   *
   * @var string|null
   */
  protected $keys;

  /**
   * A URL to which the suggestion should redirect.
   *
   * @var \Drupal\Core\Url|null
   */
  protected $url;

  /**
   * For special suggestions, some kind of HTML prefix describing them.
   *
   * @var string|null
   */
  protected $prefix;

  /**
   * A suggested prefix for the entered input.
   *
   * @var string|null
   */
  protected $suggestionPrefix;

  /**
   * The input entered by the user. Defaults to $user_input.
   *
   * @var string|null
   */
  protected $userInput;

  /**
   * A suggested suffix for the entered input.
   *
   * @var string|null
   */
  protected $suggestionSuffix;

  /**
   * If available, the estimated number of results for these keys.
   *
   * @var int|null
   */
  protected $results;

  /**
   * If given, an HTML string or render array.
   *
   * @var array|null
   */
  protected $render;

  /**
   * Constructs a Suggestion object.
   *
   * @param string|null $keys
   *   (optional) The keys.
   * @param \Drupal\Core\Url|null $url
   *   (optional) The url.
   * @param string|null $prefix
   *   (optional) The prefix.
   * @param string|null $suggestion_prefix
   *   (optional) The suggestion prefix.
   * @param string|null $user_input
   *   (optional) The user input.
   * @param string|null $suggestion_suffix
   *   (optional) The suggestion suffix.
   * @param int|null $results
   *   (optional) The number of results.
   * @param array|null $render
   *   (optional) The render array.
   */
  public function __construct($keys = NULL, Url $url = NULL, $prefix = NULL, $suggestion_prefix = NULL, $user_input = NULL, $suggestion_suffix = NULL, $results = NULL, array $render = NULL) {
    $this->keys = $keys;
    $this->url = $url;
    $this->prefix = $prefix;
    $this->suggestionPrefix = $suggestion_prefix;
    $this->userInput = $user_input;
    $this->suggestionSuffix = $suggestion_suffix;
    $this->results = $results;
    $this->render = $render;
  }

  /**
   * Creates a new suggestion from a string.
   *
   * @param string $suggestion
   *   The suggestion string.
   * @param string $user_input
   *   The user input.
   *
   * @return static
   */
  public static function fromSuggestedKeys($suggestion, $user_input) {
    $pos = Unicode::strpos($suggestion, $user_input);
    if ($pos === FALSE) {
      return new static(NULL, NULL, NULL, NULL, NULL, $suggestion);
    }
    else {
      $prefix = Unicode::substr($suggestion, 0, $pos);
      $pos += Unicode::strlen($user_input);
      $suffix = Unicode::substr($suggestion, $pos);
      return new static(NULL, NULL, NULL, $prefix, $user_input, $suffix);
    }
  }

  /**
   * Creates a suggestion from a suggestion suffix.
   *
   * @param string $suggestion_suffix
   *   The suggestion suffix.
   * @param int $results
   *   (optional) The amount of results.
   * @param string $user_input
   *   (optional) The user input.
   *
   * @return static
   */
  public static function fromSuggestionSuffix($suggestion_suffix, $results = 0, $user_input = NULL) {
    return new static(NULL, NULL, NULL, NULL, $user_input, $suggestion_suffix, $results);
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys() {
    if ($this->keys) {
      return $this->keys;
    }
    return $this->suggestionPrefix . $this->userInput . $this->suggestionSuffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionPrefix() {
    return $this->suggestionPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInput() {
    return $this->userInput;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionSuffix() {
    return $this->suggestionSuffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getRender() {
    return $this->render;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeys($keys) {
    $this->keys = $keys;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->url = $url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrefix($prefix) {
    $this->prefix = $prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggestionPrefix($suggestion_prefix) {
    $this->suggestionPrefix = $suggestion_prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserInput($user_input) {
    $this->userInput = $user_input;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggestionSuffix($suggestion_suffix) {
    $this->suggestionSuffix = $suggestion_suffix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setResults($results) {
    $this->results = $results;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRender($render) {
    $this->render = $render;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    if (!empty($this->render)) {
      return $this->render;
    }
    else {
      return [
        '#theme' => 'search_api_autocomplete_suggestion',
        '#keys' => $this->getKeys(),
        '#prefix' => $this->getPrefix(),
        '#results' => $this->getResults(),
        '#suggestion_prefix' => $this->getSuggestionPrefix(),
        '#suggestion_suffix' => $this->getSuggestionSuffix(),
        '#url' => $this->getUrl(),
        '#user_input' => $this->getUserInput(),
      ];
    }
  }

}
