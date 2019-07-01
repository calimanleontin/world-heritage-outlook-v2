<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

class IucnExportController extends ControllerBase {

  /**
   * Export the assessment as a DOC file.
   *
   * @param \Drupal\node\NodeInterface $node
   */
  public function docExport(NodeInterface $node) {
    //    //Open template with ${table}
    //    $template_document = new \PhpOffice\PhpWord\TemplateProcessor('/home/stefan/Desktop/template.docx');
    //
    //    // Replace mark by xml code of table
    //    $template_document->setValue('title', 'xddd');
    //
    //    //save template with table
    //    $template_document->saveAs('/home/stefan/Desktop/template-res.docx');
    //
    return ['#markup' => 'xd'];
  }

}