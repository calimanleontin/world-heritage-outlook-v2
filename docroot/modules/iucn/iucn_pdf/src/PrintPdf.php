<?php

namespace Drupal\iucn_pdf;

use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\node\Entity\Node;

/**
 * The print builder service.
 */
class PrintPdf implements PrintPdfInterface {

  /**
   * The Print builder.
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
  protected $queueFactory;

  protected $directoryName;

  protected $currentLanguage;

  /**
   * Queue.
   *
   * @var \Drupal\iucn_pdf\Plugin\QueueWorker\IucnPdfWorker
   */
  protected $queue;

  protected $printEngine;

  /**
   * Constructs a new EntityPrintPrintBuilder.
   *
   * @param \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface $plugin_manager
   *   Plugin manager for our Print engines.
   * @param \Drupal\entity_print\PrintBuilderInterface $print_builder
   *   Main print controller.
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
    $this->directoryName = 'download_pdf';
    $this->currentLanguage = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $this->queue = $this->queueFactory->get('iucn_pdf');
    $this->queue->createQueue();
  }

  /**
   * Get entity language.
   */
  protected function getLanguage($entity) {
    $language = $this->currentLanguage;
    $languages = $entity->getTranslationLanguages();
    // Set to default language if no translations.
    if (!isset($languages[$language])) {
      $language = 'en';
    }
    return $language;
  }

  /**
   * Put all site assessments into queue.
   */
  public function queueAllPdfFiles() {
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', 'site_assessment');
    $entity_ids = $query->execute();
    if (!empty($entity_ids)) {
      foreach ($entity_ids as $entity_id) {
        // todo...
        $this->queue->createItem(
          [
            'site_assessment' => $entity_id,
          ]
        );
      }
    }
    return count($entity_ids);
  }

  /**
   * Add site into queue.
   */
  public function addToQueue(EntityInterface $entity) {
    if ($entity->bundle() == 'site') {
      if ($entity->hasField('field_assessments')) {
        if ($entity->field_assessments->count()) {
          foreach ($entity->field_assessments as $idx => $item) {
            if (empty($item->entity)) {
              continue;
            }
            \Drupal::service('logger.factory')->get('iucn_cron')->info('[addToQueue site] createItem site=@site year=@year site_assessment=@site_assessment',
              [
                '@site' => $entity->id(),
                '@site_assessment' => $item->entity->id(),
                '@year' => $item->entity->field_as_cycle->value,
              ]
            );

            $this->queue->createItem(
              [
                'site' => $entity->id(),
                'site_assessment' => $item->entity->id(),
                'year' => $item->entity->field_as_cycle->value,
              ]
            );
          }
        }
      }

    }
    if ($entity->bundle() == 'site_assessment' && !empty($entity->field_as_site->entity)) {
      \Drupal::service('logger.factory')->get('iucn_cron')->info('[addToQueue site_assessment] createItem site=@site year=@year site_assessment=@site_assessment',
        [
          '@site' => $entity->field_as_site->entity->id(),
          '@site_assessment' => $entity->id(),
          '@year' => $entity->field_as_cycle->value,
        ]
      );
      $this->queue->createItem(
        [
          'site' => $entity->field_as_site->entity->id(),
          'site_assessment' => $entity->id(),
          'year' => $entity->field_as_cycle->value,
        ]
      );
    }
  }

  /**
   * Get year from request.
   */
  public function getYear($entity, $year = NULL) {
    /* @var \Drupal\iucn_pdf\ParamHelper $param_helper  */
    $param_helper = \Drupal::service('iucn_pdf.param_helper');
    if ($year) {
      $param_helper->overrideValue('year', $year);
      return $year;
    }
    return $param_helper->get('year');
  }

  /**
   * Generate pdf file from sites.
   */
  public function runCron() {
    global $base_url;

    $cron_config = \Drupal::configFactory()->getEditable('iucn_pdf.settings');
    $limit = $cron_config->get('sites_per_cron');
    $count = $this->queue->numberOfItems();

    $count = min($count, $limit);
    if (!$count) {
      return;
    }

    $current_langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    for ($i = 1; $i <= $count; $i++) {
      $item = $this->queue->claimItem(60);

      $year = @$item->data['year'];
      $entity_id = @$item->data['site'];
      $site_assessment = $item->data['site_assessment'];

      if (!$entity_id) {
        $entity = $this->entityTypeManager->getStorage('node')->load($site_assessment);
        $entity_id = $entity->field_as_site->entity->id();
        $year = $entity->field_as_cycle->value;
      }
      if (!$entity_id) {
        $this->queue->deleteItem($item);
        return;
      }

      $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
      if (!$entity) {
        $this->queue->deleteItem($item);
        return;
      }
      $this->getYear($entity, $year);

      /** @var \Drupal\node\Entity\Node $entity */
      $languages = array_keys($entity->getTranslationLanguages());
      foreach ($languages as $langcode) {
        $url = $base_url . '/';
        if ($langcode != 'en') {
          $url .= $langcode . '/';
        }
        $url .= 'node/' . $entity_id . '/pdf?year=' . $year;

        \Drupal::service('logger.factory')->get('pdf_debug')->info('file_get_contents @url', ['@url' => $url]);

        file_get_contents($url);
        $this->queue->deleteItem($item);
      }
    }
  }

  /**
   * Save entity as pdf.
   */
  public function savePrintable(EntityInterface $entity, $file_path) {
    return $this->printBuilder->savePrintable([$entity], $this->printEngine, 'public', $file_path);
  }

  /**
   * Pdf filename.
   */
  public function getFilename($entity_id, $language, $year) {
    $entity = Node::load($entity_id);
    $filename = \Drupal::transliteration()->transliterate($entity->getTitle(), 'en', '');
    $filename = preg_replace('/[^a-zA-Z0-9\-\._]/',' ', $filename) . ' - ' . $year . ' COA - ' . $language . '.pdf';
    return $filename;
  }

  /**
   * Pdf relative file path.
   */
  public function getFilePath($entity_id, $language, $year) {
    return $this->directoryName . '/' . $this->getFilename($entity_id, $language, $year);
  }

  /**
   * Pdf real file path.
   */
  public function getRealPath($entity_id, $language, $year) {
    $file_directory = \Drupal::service('file_system')->realpath("public://");
    return $file_directory . '/' . $this->getFilePath($entity_id, $language, $year);
  }

}
