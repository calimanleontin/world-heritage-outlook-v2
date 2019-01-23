<?php

namespace Drupal\raven\Commands;

use Drupal\Core\Serialization\Yaml;
use Drupal\raven\Logger\Raven;
use Drush\Commands\DrushCommands;
use Exception;

/**
 * Provides Drush commands for Raven module.
 */
class RavenCommands extends DrushCommands {

  /**
   * The @logger.raven service.
   *
   * @var Drupal\raven\Logger\Raven|null
   */
  protected $ravenLogger;

  /**
   * Injects Raven logger service.
   */
  public function setRavenLogger(Raven $raven_logger) {
    $this->ravenLogger = $raven_logger;
  }

  /**
   * Sends a test message to Sentry.
   *
   * Because messages are sent to Sentry asynchronously, there is no guarantee
   * that the message was actually delivered successfully.
   *
   * @param string $message
   *   The message text.
   * @param array $options
   *   An associative array of options.
   *
   * @option level
   *   The message level (debug, info, warning, error, fatal).
   * @option logger
   *   The logger.
   *
   * @command raven:captureMessage
   */
  public function captureMessage($message = 'Test message from Drush.', array $options = ['level' => 'info', 'logger' => 'drush']) {
    if (!$this->ravenLogger) {
      throw new Exception('Raven logger service not available.');
    }
    if (!$this->ravenLogger->client) {
      throw new Exception('Raven client not available.');
    }
    $id = $this->ravenLogger->client->captureMessage($message, [], ['level' => $options['level'], 'logger' => $options['logger']]);
    if (!$id) {
      throw new Exception('Send failed.');
    }
    $this->logger()->success(dt('Message sent as event %id.', ['%id' => $id]));
  }

  /**
   * Copies library and updates version in raven.libraries.yml.
   *
   * @command raven:updateLibrary
   */
  public function updateLibrary() {
    $path = drupal_get_path('module', 'raven');
    if (!copy("$path/node_modules/@sentry/browser/build/bundle.min.js", "$path/js/sentry-browser/bundle.min.js")) {
      throw new Exception('Failed to copy bundle.min.js from node_modules/@sentry/browser/build to js/sentry-browser.');
    }
    if (!copy("$path/node_modules/@sentry/browser/build/bundle.min.js.map", "$path/js/sentry-browser/bundle.min.js.map")) {
      throw new Exception('Failed to copy bundle.min.js.map from node_modules/@sentry/browser/build to js/sentry-browser.');
    }
    $version = json_decode(file_get_contents("$path/package-lock.json"))->dependencies->{'@sentry/browser'}->version;
    if (!$version) {
      throw new Exception('No version found in package-lock.json.');
    }
    $file = "$path/raven.libraries.yml";
    $libraries = Yaml::decode(file_get_contents($file));
    if (!$libraries) {
      throw new Exception('No libraries found in raven.libraries.yml.');
    }
    $libraries['sentry-browser']['version'] = $version;
    if (!file_put_contents($file, Yaml::encode($libraries))) {
      throw new Exception('Nothing written to raven.libraries.yml.');
    }
    $this->logger()->success(dt('Achievement unlocked.'));
  }

}