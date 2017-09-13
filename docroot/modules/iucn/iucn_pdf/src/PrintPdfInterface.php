<?php

namespace Drupal\iucn_pdf;

use Drupal\Core\Entity\EntityInterface;
/**
 * Interface for the Print builder service.
 */
interface PrintPdfInterface {

  /**
   * Render any content entity as a Print.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * node to save in file in pdf format
   * @param string $file_path
   *
   * @return string
   *   FALSE or the URI to the file. E.g. public://my-file.pdf.
   */
  public function savePrintable(EntityInterface $entity, $file_path);

}
