<?php

namespace Drupal\Tests\search_api_autocomplete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Entity\Search;

/**
 * Tests saving a Search API autocomplete config entity.
 *
 * @group search_api_autocomplete
 */
class SearchCrudTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_autocomplete',
    'search_api',
    'search_api_db',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $server = Server::create([
      'id' => 'server',
      'name' => 'Server &!_1',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ]);
    $server->save();

    $index = Index::create([
      'id' => 'index',
      'name' => 'Index !1%$_',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:user' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $index->setServer($server);
    $index->save();
  }

  /**
   * Creates and saves an autocomplete entity.
   */
  public function testCreate() {
    $autocomplete_search = Search::create([
      'id' => 'muh',
      'label' => 'Meh',
      'index_id' => 'index',
      'suggester' => 'server',
      'type' => 'test_type',
      'options' => [
        'delay' => 1338,
      ],
    ]);
    $autocomplete_search->save();
  }

}
