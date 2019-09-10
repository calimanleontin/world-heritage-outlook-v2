<?php

namespace Drupal\Tests\raven\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Site\Settings;
use Drupal\raven\Logger\Raven;
use Drupal\Tests\UnitTestCase;

/**
 * Test proxy configuration.
 *
 * @group raven
 */
class ProxyConfigTest extends UnitTestCase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Environment.
   *
   * @var string
   */
  protected $environment;

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->parser = $this->createMock(LogMessageParserInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->environment = 'testing';
  }

  /**
   * Data provider for testProxyConfiguration().
   */
  public function proxyConfigurationData() {
    return [
      // HTTP DSN, Empty proxy white-list.
      [
        'http://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => NULL, 'no' => []],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        ['http' => 'http-proxy.server.test:3129', 'https' => NULL, 'no' => []],
        'http',
      ],
      [
        'http://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => 'https-proxy.server.test:3129', 'no' => []],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => [],
        ],
        'http',
      ],
      // HTTP DSN, Not empty proxy white-list.
      [
        'http://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => NULL, 'no' => ['some.server.test']],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => NULL,
          'no' => ['some.server.test'],
        ],
        'http',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test'],
        ],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test'],
        ],
        'http',
      ],
      // HTTP DSN, Not empty proxy white-list, Sentry white-listed.
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => NULL,
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => NULL,
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'http://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      // HTTPS DSN, Empty proxy white-list.
      [
        'https://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => NULL, 'no' => []],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        ['http' => 'http-proxy.server.test:3129', 'https' => NULL, 'no' => []],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => 'https-proxy.server.test:3129', 'no' => []],
        'https',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => [],
        ],
        'https',
      ],
      // HTTPS DSN, Not empty proxy white-list.
      [
        'https://user:password@sentry.test/123456',
        ['http' => NULL, 'https' => NULL, 'no' => ['some.server.test']],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => NULL,
          'no' => ['some.server.test'],
        ],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test'],
        ],
        'https',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test'],
        ],
        'https',
      ],
      // HTTPS DSN, Not empty proxy white-list, Sentry white-listed.
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => NULL,
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => NULL,
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => NULL,
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
      [
        'https://user:password@sentry.test/123456',
        [
          'http' => 'http-proxy.server.test:3129',
          'https' => 'https-proxy.server.test:3129',
          'no' => ['some.server.test', 'sentry.test'],
        ],
        'no',
      ],
    ];
  }

  /**
   * Test proxy configuration.
   *
   * @dataProvider proxyConfigurationData
   */
  public function testProxyConfiguration($dsn, $config, $proxy) {
    $this->configFactory = $this->getConfigFactoryStub([
      'raven.settings' => [
        'client_key' => $dsn,
        // We need this to avoid registering error handlers in
        // \Drupal\raven\Logger\Raven constructor.
        'fatal_error_handler' => FALSE,
      ],
    ]);

    new Settings([
      'http_client_config' => [
        'proxy' => $config,
      ],
    ]);

    $raven = new Raven($this->configFactory, $this->parser, $this->moduleHandler, $this->environment);
    if ($proxy === 'no') {
      self::assertEmpty($raven->client->http_proxy, 'No proxy configured for Raven_Client');
    }
    else {
      self::assertSame($config[$proxy], $raven->client->http_proxy, strtoupper($proxy) . ' proxy configured for Raven_Client');
    }
  }

}
