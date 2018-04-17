<?php
namespace Drupal\iucn_config_ignore\Plugin\ConfigIgnore;

use Drupal\Core\Plugin\PluginBase;
use Drupal\config_ignore_keys\Plugin\ConfigurationIgnorePluginInterface;

/**
 * Class ContactFormIgnore.
 *
 * @ConfigurationIgnorePlugin(
 *   id = "contact_form_ignore",
 *   description = "Ignoring the receipients of the contact form configuration",
 * )
 */
class ConfigIgnore extends PluginBase implements ConfigurationIgnorePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigurations() {
    return [
      'system.site' => [
        'mail',
      ],
      'webform.webform.ask_a_question' => [
        'handlers.email.settings.to_mail',
      ],
      'webform.webform.site_feedback' => [
        'handlers.email.settings.to_mail',
      ],
    ];
  }
}
