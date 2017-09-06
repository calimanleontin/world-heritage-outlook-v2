<?php

namespace Drupal\iucn_migrate\Plugin\migrate\source;

use Drupal\Core\Config\Config;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate\source\SourcePluginExtension;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Exception\RequestException;

/**
 * A source class for Url resources.
 *
 * @MigrateSource(
 *   id = "iucn_migrate_source_json"
 * )
 */
class SourceJson extends SourcePluginExtension {

  /**
   * Current item when iterating.
   *
   * @var mixed
   */
  protected $currentItem = NULL;

  /**
   * {@inheritdoc}
   */
  protected $trackChanges = TRUE;

  /**
   * Iterator over the JSON data.
   *
   * @var \Iterator
   */
  protected $iterator;

  /**
   * Path to the source file.
   *
   * @var string
   */
  protected $sourceUrl;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {

    // Make URLs required.
    if (!isset($configuration['path_setting'])) {
      throw new MigrateException('path_setting is not defined');
    }

    $module_config = \Drupal::config('iucn_migration.settings');
    $this->sourceUrl = $module_config->get($configuration['path_setting']);

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  public function __toString() {
    return $this->sourceUrl;
  }

  public function getUrl() {
    return $this->sourceUrl;
  }

  /**
   * Returns the initialized data parser plugin.
   *
   * @return \Drupal\migrate_plus\DataParserPluginInterface
   *   The data parser plugin.
   */
  public function getDataParserPlugin() {
    if (!isset($this->dataParserPlugin)) {
      $this->dataParserPlugin = \Drupal::service('plugin.manager.migrate_plus.data_parser')->createInstance('iucn_json', $this->configuration);
    }
    return $this->dataParserPlugin;
  }

  /**
   * Creates and returns a filtered Iterator over the documents.
   *
   * @return \Iterator
   *   An iterator over the documents providing source rows that match the
   *   configured item_selector.
   */
  protected function initializeIterator() {
    $source_data = $this->getSourceData($this->getUrl());
    $this->iterator = new \ArrayIterator($source_data);
    return $this->iterator;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData($url) {
    $response = file_get_contents($url);
    $items = json_decode($response, TRUE);

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $current = $this->iterator->current();
    if ($current) {
      $this->iterator->next();
    }
  }

}
