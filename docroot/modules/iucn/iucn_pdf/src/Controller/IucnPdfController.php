<?php

namespace Drupal\iucn_pdf\Controller;

use Drupal\iucn_pdf\PrintPdfInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Drupal\system\FileDownloadController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\entity_print\PrintBuilderInterface;

/**
 * Print controller.
 */
class IucnPdfController extends FileDownloadController {

  /**
   * The plugin manager for our Print engines.
   *
   * @var \Drupal\iucn_pdf\PrintPdfInterface
   */
  protected $printPdf;

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

  protected $currentLanguage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    PrintPdfInterface $print_pdf,
    PrintBuilderInterface $print_builder,
    EntityTypeManagerInterface $entity_type_manager
  ) {

    $this->printPdf = $print_pdf;
    $this->printBuilder = $print_builder;
    $this->entityTypeManager = $entity_type_manager;

    $this->currentLanguage = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('iucn_pdf.print_pdf'),
      $container->get('entity_print.print_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get entity language.
   */
  public function getLanguage($entity) {
    $language = $this->currentLanguage;
    $languages = $entity->getTranslationLanguages();
    // Set to default language if no translations.
    if (!isset($languages[$language])) {
      $language = 'en';
    }
    return $language;
  }

  /**
   * Get year form Request with possibility to override.
   */
  public function getYear() {
    $param_helper = \Drupal::service('iucn_pdf.param_helper');
    $year = $param_helper->get('year');
    return $year;
  }

  /**
   * A debug callback for styling up the Print.
   *
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   *
   * @TODO, improve permissions in https://www.drupal.org/node/2759553
   */
  public function downloadPdfDebug($entity_id) {
    $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
    return new Response($this->printBuilder->printHtml($entity, FALSE, FALSE));
  }

  /**
   * Print an entity to PDF format.
   *
   * @param int $entity_id
   *   The entity id.
   *
   * The file to stream
   * @return File
   */
  public function downloadPdf($entity_id) {

    /* @var \Drupal\iucn_pdf\ParamHelper $param_helper  */

    $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
    $language = $this->getLanguage($entity);
    $year = $this->getYear();

    $realpath = $this->printPdf->getRealPath($entity_id, $language, $year);
    $file_path = $this->printPdf->getFilePath($entity_id, $language, $year);

    if (!file_exists($realpath)) {
      $this->printPdf->savePrintable($entity, $file_path);
    }
    if (!file_exists($realpath)) {
      throw new NotFoundHttpException();
    }

    $filename = $this->printPdf->getFilename($entity_id, $language, $year);
    $mime_type = Unicode::mimeHeaderEncode('application/pdf');
    $headers = [
      'Content-Type' => $mime_type,
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Content-Length' => filesize($realpath),
      'Content-Transfer-Encoding' => 'binary',
      'Pragma' => 'no-cache',
      'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
      'Expires' => '0',
      'Accept-Ranges' => 'bytes',
    ];
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    // sets response as not cacheable if the Cache-Control header is not
    // already modified. We pass in FALSE for non-private schemes for the
    // $public parameter to make sure we don't change the headers.
    return new BinaryFileResponse($realpath, 200, $headers, TRUE);
  }

}
