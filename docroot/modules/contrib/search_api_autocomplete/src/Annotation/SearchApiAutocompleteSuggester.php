<?php

namespace Drupal\search_api_autocomplete\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a autocomplete suggester plugin.
 *
 * @Annotation
 *
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterInterface
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterManager
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase
 */
class SearchApiAutocompleteSuggester extends Plugin {

  /**
   * The plugin label.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The plugin description.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
