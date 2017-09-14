<?php

namespace Drupal\iucn_pdf;

use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * The print builder service.
 */
class PrintPdf implements PrintPdfInterface {

  /**
   * The Print builder.
   *
   * @var \Drupal\entity_print\PrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue_factory;

  protected $directory_name;

  protected $current_language;

  /**
   *
   *
   * @var \Drupal\iucn_pdf\Plugin\QueueWorker\IucnPdfWorker $queue
   */
  protected $queue;

  /**
   * Constructs a new EntityPrintPrintBuilder.
   *
   * @param \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface $plugin_manager
   *   Plugin manager for our Print engines.
   * @param \Drupal\entity_print\PrintBuilderInterface $print_builder
   *   Main print controller
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Helper object for entity load.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue service for node_ids.
   */
  public function __construct(EntityPrintPluginManagerInterface $plugin_manager,
                              PrintBuilderInterface $print_builder,
                              EntityTypeManagerInterface $entity_type_manager,
                              QueueFactory $queue_factory) {

    $this->printBuilder = $print_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;

    $this->printEngine = $plugin_manager->createSelectedInstance('pdf');
    $this->directory_name = 'download_pdf';
    $this->current_language = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    // init queue
    $this->queue = $this->queueFactory->get('uicn_pdf');
    $this->queue->createQueue();
  }

  protected function getLanguage($entity) {
    $language = $this->current_language;
    $languages = $entity->getTranslationLanguages();
    if (!isset($languages[$language])) { // set to defualt language if no translations
      $language = 'en';
    }
    return $language;
  }

  /**
   *  Put all site assessments into queue.
   */
  public function queueAllPdfFiles() {

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', 'site_assessment');
    $entity_ids = $query->execute();
    if (!empty($entity_ids)) {
      foreach ($entity_ids as $entity_id) {
//        \Drupal::service('logger.factory')->get('iucn_cron')->info('[queueAllPdfFiles]: entity_id=@entity_id', [ '@entity_id' => $entity_id]);
        $this->queue->createItem($entity_id);
      }
    }
    return count($entity_ids);
  }

  public function runCron() {
    $count = $this->queue->numberOfItems();
    $limit = 5;//todo move in config

    $count = min($count, $limit);
    if (!$count) {
      return;
    }

    $current_langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    for ($i = 1; $i <= $count; $i++) {
      $item = $this->queue->claimItem(60);
      $entity_id = $item->data;
      if (!$entity_id) {
        $this->queue->deleteItem($item);
        return;
      }
      $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
      if (!$entity) {
        $this->queue->deleteItem($item);
        return;
      }
      /** @var \Drupal\node\Entity\Node $entity */
      $languages = array_keys($entity->getTranslationLanguages());
      foreach($languages as $langcode) {

        $tmp_language = \Drupal::languageManager()->getLanguage($langcode);
        \Drupal::service('language.default')->set($tmp_language);

//  \Drupal::service('logger.factory')->get('iucn_cron')->info('[savePrintable]: entity_id=@entity_id, language=@language',
//    [ '@entity_id' => $entity_id,
//      '@langcode' => $langcode
//    ]);

        $file_path = $this->getFilePath($entity_id, $langcode);
        $this->savePrintable($entity, $file_path);
        $this->queue->deleteItem($item);
      }
    }

    $current_language = \Drupal::languageManager()->getLanguage($current_langcode);
    \Drupal::service('language.default')->set($current_language);
  }

  public function savePrintable(EntityInterface $entity, $file_path) {
    return $this->printBuilder->savePrintable([$entity], $this->printEngine, 'public', $file_path);
  }

  public function addToQueue(EntityInterface $entity) {
    $this->queue->createItem($entity->id());
  }

  public function getFilename($entity_id, $language) {
    return $entity_id . '-' . $language . '.pdf';
  }

  public function getFilePath($entity_id, $language) {
    return $this->directory_name . '/' . $this->getFilename($entity_id, $language);
  }

  public function getRealPath($entity_id, $language) {
    $file_directory = \Drupal::service('file_system')->realpath("public://");
    return $file_directory . '/' . $this->getFilePath($entity_id, $language);
  }

}
