<?php

namespace Drupal\search_api_autocomplete\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Describes the autocomplete settings for a certain search.
 *
 * @ConfigEntityType(
 *   id = "search_api_autocomplete_search",
 *   label = @Translation("Autocomplete search"),
 *   handlers = {
 *     "form" = {
 *       "default" = "\Drupal\search_api_autocomplete\Form\SearchEditForm",
 *       "edit" = "\Drupal\search_api_autocomplete\Form\SearchEditForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "\Drupal\Core\Entity\EntityListBuilder",
 *     "route_provider" = {
 *       "default" = "\Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer search_api_autocomplete",
 *   config_prefix = "search_api_autocomplete",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/search/search-api/index/autocomplete/{search_api_autocomplete_search}/edit",
 *     "delete-form" = "/admin/config/search/search-api/index/autocomplete/{search_api_autocomplete_search}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "index_id",
 *     "suggester",
 *     "suggester_settings",
 *     "type",
 *     "type_settings",
 *     "options",
 *   }
 * )
 */
class Search extends ConfigEntityBase implements SearchInterface {

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity label.
   *
   * @var string
   */
  protected $label;

  /**
   * The index ID.
   *
   * @var string
   */
  protected $index_id;

  /**
   * The search index instance.
   *
   * @var \Drupal\search_api\IndexInterface|null
   *
   * @see \Drupal\search_api_autocomplete\Entity\Search::getIndexInstance()
   */
  protected $index;

  /**
   * The suggester ID.
   *
   * @var string
   */
  protected $suggester;

  /**
   * Settings for the suggester plugin.
   *
   * @var array
   */
  protected $suggester_settings = [];

  /**
   * The suggester plugin.
   *
   * @var \Drupal\search_api_autocomplete\Suggester\SuggesterInterface|null
   */
  protected $suggesterInstance;

  /**
   * The autocomplete type.
   *
   * @var string
   */
  protected $type;

  /**
   * Settings for the type plugin.
   *
   * @var string
   */
  protected $type_settings = [];

  /**
   * The type plugin.
   *
   * @var \Drupal\search_api_autocomplete\Type\TypeInterface|null
   */
  protected $typeInstance;

  /**
   * An array of general options for this search.
   *
   * @var array
   */
  protected $options = [];

  /**
   * {@inheritdoc}
   */
  public function getSuggesterId() {
    return $this->suggester;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggesterId($suggester_id) {
    $this->suggester = $suggester_id;
    unset($this->suggesterInstance);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterInstance($reset = FALSE) {
    // @todo Throw exception for failures and get rid of $reset.
    if (!isset($this->suggesterInstance) || $reset) {
      $configuration = $this->suggester_settings;
      $configuration['#search'] = $this;
      $this->suggesterInstance = \Drupal::getContainer()
        ->get('plugin.manager.search_api_autocomplete.suggester')
        ->createInstance($this->suggester, $configuration);
      if (!$this->suggesterInstance) {
        $variables['@search'] = $this->id();
        $variables['@index'] = $this->getIndexInstance() ? $this->getIndexInstance()->label() : $this->index_id;
        $variables['@suggester'] = $this->suggester;
        $this->getLogger()->error('Autocomplete search @search on index @index specifies an invalid suggesterInstance plugin @suggester.', $variables);
        $this->suggesterInstance = FALSE;
      }
    }
    return $this->suggesterInstance ? $this->suggesterInstance : NULL;
  }

  /**
   * Returns a logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   A logger instance.
   */
  protected function getLogger() {
    return \Drupal::logger('search_api_autocomplete');
  }

  /**
   * {@inheritdoc}
   */
  public function supportsAutocompletion() {
    return $this->getIndexInstance() && $this->getSuggesterInstance() && $this->getSuggesterInstance()->supportsIndex($this->getIndexInstance());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery($keys) {
    $type = $this->getTypeInstance();
    $query = $type->createQuery($this, $keys);
    if ($keys && !$query->getKeys()) {
      $query->keys($keys);
    }
    return $query;
  }

  /**
   * Returns the autocomplete instance for this autocomplete search.
   *
   * @return \Drupal\search_api_autocomplete\Type\TypeInterface
   *   The autocomplete type instance.
   */
  protected function getTypeInstance() {
    return \Drupal::service('plugin.manager.search_api_autocomplete.type')
      ->createInstance($this->getType());
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId() {
    return $this->index_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexInstance() {
    if (!isset($this->index)) {
      $this->index = Index::load($this->index_id);
      if (!$this->index) {
        // @todo Should throw exception. (Also, should never happen.)
        $this->index = FALSE;
      }
    }
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function setIndexId($index_id) {
    $this->index_id = $index_id;
    $this->index = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($key, $default = NULL) {
    $parts = explode('.', $key);
    $value = NestedArray::getValue($this->options, $parts, $key_exists);
    return $key_exists ? $value : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency($this->getIndexInstance()->getConfigDependencyKey(), $this->getIndexInstance()->getConfigDependencyName());
    // @todo Dependencies for display, and for plugins (providers).
    return $this;
  }

}
