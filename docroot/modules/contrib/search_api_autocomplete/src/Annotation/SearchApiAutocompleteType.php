<?php

namespace Drupal\search_api_autocomplete\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an autocompletion type.
 *
 * @Annotation
 *
 * @see \Drupal\search_api_autocomplete\Type\TypeInterface
 * @see \Drupal\search_api_autocomplete\Type\TypeManager
 */
class SearchApiAutocompleteType extends Plugin {

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
