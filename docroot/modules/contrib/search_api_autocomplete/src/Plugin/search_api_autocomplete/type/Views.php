<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\type;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Type\TypePluginBase;
use Drupal\views\Views as ViewsViews;

/**
 * Provides autocomplete support for Views search.
 *
 * @SearchApiAutocompleteType(
 *   id = "views",
 *   label = @Translation("Search views"),
 *   description = @Translation("Searches provided by Views"),
 *   provider = "search_api",
 * )
 */
class Views extends TypePluginBase implements ConfigurablePluginInterface, PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
    $search = $form_state->getFormObject()->getEntity();
    $views_id = substr($search->id(), 17);
    $view = ViewsViews::getView($views_id);
    $options = [];
    $view->initDisplay();
    foreach ($view->displayHandlers as $id => $display) {
      /** @var \Drupal\views\Plugin\views\display\DisplayPluginBase $display */
      $options[$id] = $display->display['display_title'];
    }
    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Views display'),
      '#description' => $this->t('Please select the Views display whose settings should be used for autocomplete queries.<br />' .
        "<strong>Note:</strong> Autocompletion doesn't work well with contextual filters. Please see the <a href=':readme_url'>README.txt</a> file for details.",
        [':readme_url' => Url::fromUri('base://' . drupal_get_path('module', 'search_api_autocomplete') . '/README.txt')->toString()]),
      '#options' => $options,
      '#default_value' => $this->configuration['display'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
    $search = $form_state->getFormObject()->getEntity();
    $views_id = substr($search->id(), 17);
    $view = ViewsViews::getView($views_id);
    $view->setDisplay($form_state->getValue('display'));
    $view->preExecute();
    if ($view->argument) {
      drupal_set_message(t('You have selected a display with contextual filters. This can lead to various problems. Please see the <a href=":readme_url">README.txt</a> file for details.',
        [':readme_url' => Url::fromUri('base://' . drupal_get_path('module', 'search_api_autocomplete') . '/README.txt')->toString()]), 'warning');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listSearches(IndexInterface $index) {
    $ret = [];
    $base_table = 'search_api_index_' . $index->id();
    foreach (ViewsViews::getAllViews() as $id => $view) {
      if ($view->get('base_table') === $base_table) {
        // @todo Check whether there is an exposed fulltext filter
        $ret['search_api_views_' . $id] = [
          'name' => $id,
        ];
      }
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function createQuery(SearchInterface $search, $keys) {
    $views_id = substr($search->id(), 17);
    $view = ViewsViews::getView($views_id);
    if (!$view) {
      $vars['@view'] = $views_id;
      throw new SearchApiException($this->t('Could not load view @view.', $vars));
    }
    $view->setDisplay($search->getOption('custom.display'));
    // @todo Find out the GET parameter used for the "Search: Fulltext search"
    //   filter and set it to $keys in the view's exposed input.
    $view->preExecute();
    $view->build();
    $query_plugin = $view->getQuery();
    if ($query_plugin instanceof SearchApiQuery) {
      $query = $query_plugin->getSearchApiQuery();
    }
    if (empty($query)) {
      $vars['@view'] = $view->storage->label() ?: $views_id;
      throw new SearchApiException($this->t('Could not create search query for view @view.', $vars));
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

}
