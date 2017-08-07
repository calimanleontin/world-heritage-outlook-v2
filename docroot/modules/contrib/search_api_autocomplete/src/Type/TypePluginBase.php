<?php

namespace Drupal\search_api_autocomplete\Type;

use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a base class for type plugins.
 */
abstract class TypePluginBase extends PluginBase implements TypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

}
