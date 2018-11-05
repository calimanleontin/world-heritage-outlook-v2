<?php

namespace Drupal\raven\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Raven_Client;
use Raven_ErrorHandler;

/**
 * Logs events to Sentry.
 */
class Raven implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * Raven client.
   *
   * @var \Raven_Client
   */
  public $client;

  /**
   * A configuration object containing syslog settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a Raven log object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $environment
   *   The kernel.environment parameter.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser, ModuleHandlerInterface $module_handler, $environment) {
    if (!class_exists('Raven_Client')) {
      // Sad raven.
      return;
    }
    $this->config = $config_factory->get('raven.settings');
    $this->moduleHandler = $module_handler;
    $this->parser = $parser;
    if (!empty($this->config->get('environment'))) {
      $environment = $this->config->get('environment');
    }
    $options = [
      'auto_log_stacks' => $this->config->get('stack'),
      'curl_method' => 'async',
      'dsn' => $this->config->get('client_key'),
      'environment' => $environment,
      'processors' => ['Drupal\raven\Processor\SanitizeDataProcessor'],
      'timeout' => $this->config->get('timeout'),
      'trace' => $this->config->get('trace'),
      'verify_ssl' => TRUE,
    ];

    $ssl = $this->config->get('ssl');
    // Verify against a CA certificate.
    if ($ssl == 'ca_cert') {
      $options['ca_cert'] = realpath($this->config->get('ca_cert'));
    }
    // Don't verify at all.
    elseif ($ssl == 'no_verify_ssl') {
      $options['verify_ssl'] = FALSE;
    }

    if (!empty($this->config->get('release'))) {
      $options['release'] = $this->config->get('release');
    }
    $this->moduleHandler->alter('raven_options', $options);
    try {
      $this->client = new Raven_Client($options);
    }
    catch (InvalidArgumentException $e) {
      // Raven is incorrectly configured.
      return;
    }
    // Raven can catch fatal errors which are not caught by the Drupal logger.
    if ($this->config->get('fatal_error_handler')) {
      $error_handler = new Raven_ErrorHandler($this->client);
      $error_handler->registerShutdownFunction($this->config->get('fatal_error_handler_memory'));
      register_shutdown_function([$this->client, 'onShutdown']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    if (!$this->client) {
      // Sad raven.
      return;
    }
    if (empty($this->config->get('log_levels')[$level + 1])) {
      return;
    }
    $levels = [
      RfcLogLevel::EMERGENCY => Raven_Client::FATAL,
      RfcLogLevel::ALERT => Raven_Client::FATAL,
      RfcLogLevel::CRITICAL => Raven_Client::FATAL,
      RfcLogLevel::ERROR => Raven_Client::ERROR,
      RfcLogLevel::WARNING => Raven_Client::WARNING,
      RfcLogLevel::NOTICE => Raven_Client::INFO,
      RfcLogLevel::INFO => Raven_Client::INFO,
      RfcLogLevel::DEBUG => Raven_Client::DEBUG,
    ];
    $data['level'] = $levels[$level];
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $data['message'] = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
    $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z', $context['timestamp']);
    $data['tags']['channel'] = $context['channel'];
    $data['extra']['link'] = $context['link'];
    $data['extra']['referer'] = $context['referer'];
    $data['extra']['request_uri'] = $context['request_uri'];
    $data['user']['id'] = $context['uid'];
    $data['user']['ip_address'] = $context['ip'];
    $stack = isset($context['backtrace']) ? $context['backtrace'] : NULL;

    // Allow modules to alter or ignore this message.
    $filter = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
      'data' => &$data,
      'stack' => &$stack,
      'client' => $this->client,
      'process' => TRUE,
    ];
    $this->moduleHandler->alter('raven_filter', $filter);
    if (!$filter['process']) {
      return;
    }

    $this->client->capture($data, $stack);
  }

}
