<?php

namespace Drupal\iucn_pdf\Controller;

use Drupal\iucn_pdf\PrintPdfInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Drupal\system\FileDownloadController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Print controller.
 */
class IucnPdfController extends FileDownloadController {

  /**
   * The plugin manager for our Print engines.
   *
   * @var \Drupal\iucn_pdf\PrintPdfInterface
   */
  protected $print_pdf;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected $current_language;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    PrintPdfInterface $print_pdf,
    EntityTypeManagerInterface $entity_type_manager
  ) {

    $this->print_pdf = $print_pdf;
    $this->entityTypeManager = $entity_type_manager;

    $this->current_language = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('iucn_pdf.print_pdf'),
      $container->get('entity_type.manager')
    );
  }

  public function getLanguage($entity) {
    $language = $this->current_language;
    $languages = $entity->getTranslationLanguages();
    if (!isset($languages[$language])) { // set to default language if no translations
      $language = 'en';
    }
    return $language;
  }

  /**
   * Print an entity to PDF format.
   *
   * @param int $entity_id
   *   The entity id.
   *
   */
  public function downloadPdf($entity_id) {
    $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
    $language = $this->getLanguage($entity);

    $realpath = $this->print_pdf->getRealPath($entity_id, $language);
    $file_path = $this->print_pdf->getFilePath($entity_id, $language);

    if (!file_exists($realpath)) {
      $this->print_pdf->savePrintable($entity, $file_path);
//      \Drupal::service('logger.factory')->get('iucn_cron')->info('[downloadPdf -> savePrintable]: entity_id=@entity_id, language=@language',
//        [ '@entity_id' => $entity_id,
//          '@language' => $language
//        ]);
    } else {
//      \Drupal::service('logger.factory')->get('iucn_cron')->info('[downloadPdf - Already exist]: entity_id=@entity_id, language=@language',
//        [ '@entity_id' => $entity_id,
//          '@language' => $language
//        ]);
    }
    if (!file_exists($realpath)) {
      throw new NotFoundHttpException();
    }

    $filename = $this->print_pdf->getFilename($entity_id, $language);
    $mimetype = Unicode::mimeHeaderEncode('application/pdf');
    $headers = [
      'Content-Type' => $mimetype,
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
