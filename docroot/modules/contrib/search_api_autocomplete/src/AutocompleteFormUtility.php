<?php

namespace Drupal\search_api_autocomplete;

use Drupal\Core\Render\RendererInterface;
use Drupal\search_api_autocomplete\Controller\AutocompleteController;

/**
 * Provides some helper methods to deal with the autocomplete form.
 *
 * @todo This should be a service.
 */
class AutocompleteFormUtility {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a new AutocompleteFormUtility instance.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Split a string with search keywords into two parts.
   *
   * The first part consists of all words the user has typed completely, the
   * second one contains the beginning of the last, possibly incomplete word.
   *
   * @param string $keys
   *   The passed in keys.
   *
   * @return array
   *   An array with $keys split into exactly two parts, both of which may be
   *   empty.
   */
  public static function splitKeys($keys) {
    $keys = ltrim($keys);
    // If there is whitespace or a quote on the right, all words have been
    // completed.
    if (rtrim($keys, " \"") != $keys) {
      return [rtrim($keys, ' '), ''];
    }
    if (preg_match('/^(.*?)\s*"?([\S]*)$/', $keys, $m)) {
      return [$m[1], $m[2]];
    }
    return ['', $keys];
  }

  /**
   * Helper method for altering a textfield form element to use autocompletion.
   *
   * @param array $element
   *   The altered element.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The autocomplete search.
   * @param array $fields
   *   (optional) Used fulltext fields.
   */
  public function alterElement(array &$element, SearchInterface $search, array $fields = []) {
    // @todo find a cleaner solution.
    $controller = new AutocompleteController(\Drupal::service('renderer'));
    if ($controller->access($search, \Drupal::currentUser())->isAllowed()) {
      // Add option defaults (in case of updates from earlier versions).
      $options = $search->getOptions() + [
        'submit_button_selector' => ':submit',
        'autosubmit' => TRUE,
        'min_length' => 1,
      ];

      $fields_string = $fields ? implode(' ', $fields) : '-';

      $autocomplete_route_name = 'search_api_autocomplete.autocomplete';
      $autocomplete_route_parameters = ['search_api_autocomplete_search' => $search->id(), 'fields' => $fields_string];

      $js_settings = [];
      if ($options['submit_button_selector'] != ':submit') {
        $js_settings['selector'] = $options['submit_button_selector'];
      }
      if ($delay = $search->getOption('delay') !== NULL) {
        $js_settings['delay'] = $delay;
      }

      // Allow overriding of the default handler with a route.
      if ($callback = $search->getOption('custom_autocomplete_url_callback')) {
        $callback_options = $search->getOption('custom_autocomplete_url_options', []);
        /** @var \Drupal\Core\Url $url */
        $url = call_user_func($callback, $this, $element, $callback_options);
        $autocomplete_route_name = $url->getRouteName();
        $autocomplete_route_parameters = $url->getRouteParameters();
        $js_settings['custom_path'] = TRUE;
      }
      $element['#attached']['library'][] = 'search_api_autocomplete/search_api_autocomplete';
      if ($js_settings) {
        $element['#attached']['drupalSettings'][] = [
          'search_api_autocomplete' => [
            $search->id() => $js_settings,
          ],
        ];
      }

      $element['#autocomplete_route_name'] = $autocomplete_route_name;
      $element['#autocomplete_route_parameters'] = $autocomplete_route_parameters;
      $element += ['#attributes' => []];
      $element['#attributes'] += ['class' => []];
      if ($options['autosubmit']) {
        $element['#attributes']['class'][] = 'auto_submit';
      }
      $element['#attributes']['data-search-api-autocomplete-search'] = $search->id();
      if ($options['min_length'] > 1) {
        $element['#attributes']['data-min-autocomplete-length'] = $options['min_length'];
      }
    }
  }

}
