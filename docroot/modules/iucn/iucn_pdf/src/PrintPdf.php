<?php

namespace Drupal\iucn_pdf;

use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
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

  protected $directoryName;

  protected $currentLanguage;

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
   */
  public function __construct(EntityPrintPluginManagerInterface $plugin_manager,
                              PrintBuilderInterface $print_builder,
                              EntityTypeManagerInterface $entity_type_manager
  ) {

    $this->printBuilder = $print_builder;
    $this->entityTypeManager = $entity_type_manager;

    $this->printEngine = $plugin_manager->createSelectedInstance('pdf');
    $this->directoryName = 'download_pdf';

    $this->currentLanguage = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
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
   * Save entity as pdf.
   */
  public function savePrintable(EntityInterface $entity, $file_path) {
    if ($entity->isPublished()) {
      return $this->printBuilder->savePrintable([$entity], $this->printEngine, 'public', $file_path);
    }
    return NULL;
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
    if ($uploaded_pdf = $this->uploadedPdf($entity_id, $language, $year)) {
      return $uploaded_pdf;
    }
    $file_directory = \Drupal::service('file_system')->realpath("public://");
    return $file_directory . '/' . $this->getFilePath($entity_id, $language, $year);
  }


  /**
   * Pdf real file path of generated PDFs.
   */
  public function getRealPathGenerated($entity_id, $language, $year) {
    $file_directory = \Drupal::service('file_system')->realpath("public://");
    return $file_directory . '/' . $this->getFilePath($entity_id, $language, $year);
  }

  /**
   * Delete pdf.
   */
  public function deletePdf(EntityInterface $entity) {
    $years = [];
    $currentYear = date('Y');
    for ($i = 2014; $i <= $currentYear; $i += 3) {
      $years[] = $i;
    }
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $lang => $language) {
      foreach ($years as $year) {
        $realpath = $this->getRealPathGenerated($entity->id(), $lang, $year);
        if (file_exists($realpath)) {
          if (unlink($realpath)) {
            \Drupal::logger('iucn_pdf')->notice('Successfully removed ' .  $realpath);
          } else {
            \Drupal::logger('iucn_pdf')->error('Could not remove ' .  $realpath);
          }
        }
      }
    }
 }

  /**
   * Check if the pdf is generated or uploaded.
   */
 public function uploadedPdf($entity_id, $language , $year ){
   $node = Node::load($entity_id);
   if ($node->hasField('field_assessments')) {
     if ($node->field_assessments->count()) {
       foreach ($node->field_assessments as $idx => $item) {
         $site_assessment_translations = $item->entity->getTranslationLanguages();
         if (isset($site_assessment_translations[$language])) {
            $assessment = $item->entity->getTranslation($language);
         }
         if ( $assessment
           && $assessment->field_as_cycle->value == $year
           && $assessment_file = $assessment->get('field_assessment_file')->getValue()
         ) {
           $fid = $assessment_file[0]['target_id'];
           if ($file = \Drupal\file\Entity\File::load($fid)) {
             /** @var \Drupal\file\Entity\File $file */
             return $file->getFileUri();
           }

         }
       }
     }
   }
   return FALSE;
 }

}
