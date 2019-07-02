<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use HTMLtoOpenXML\Parser;
use PhpOffice\PhpWord\TemplateProcessor;

class IucnExportController extends ControllerBase {

  /**
   * Export the assessment as a DOC file.
   *
   * @param \Drupal\node\NodeInterface $node
   */
  public function docExport(NodeInterface $node) {
    $templateDocument = new \PhpOffice\PhpWord\TemplateProcessor(drupal_get_path('module', 'iucn_assessment') . '/data/export/assessment_export_tpl.docx');
    $fields = $this->getTemplateFields($templateDocument);

    foreach ($fields as $field => $referencedFields) {
      if (!is_array($referencedFields)) {
        $templateDocument->setValue($field, $this->getFieldValue($node, $field));
        continue;
      }

      $paragraphFieldList = $node->get($field);
      $arrayKeys = array_keys($referencedFields);
      $firstParagraphField = reset($arrayKeys);
      $templateDocument->cloneRow($firstParagraphField, count($paragraphFieldList));

      if ($firstParagraphField == 'field_as_values_wh.index') {
        $templateDocument->cloneRow($firstParagraphField, count($paragraphFieldList));
      }

      foreach ($paragraphFieldList as $idx => $fieldItem) {
        $this->writeParagraphToTemplate($templateDocument, $fieldItem->entity, $referencedFields, $idx + 1);
      }

    }

    $templateDocument->saveAs('/home/stefan/work/iucn/docroot/sites/default/files/template-res.docx');
    return ['#markup' => 'xd', '#cache' => ['max-age' => 0]];
  }

  protected function getFieldValue(FieldableEntityInterface $entity, $field) {
    $fieldRender = $entity->get($field)->view(['label' => 'hidden']);
    $fieldRender = \Drupal::service('renderer')->render($fieldRender);
    $fieldRender = $this->stripTagsContent($fieldRender, '<button>', TRUE);
    return strip_tags($fieldRender);
  }

  protected function writeParagraphToTemplate(TemplateProcessor $templateProcessor, ParagraphInterface $paragraph, $fields, $index) {
    foreach ($fields as $templateVariable => $field) {
      if (!$paragraph->hasField($field)) {
        continue;
      }

      $templateProcessor->setValue("$templateVariable#$index", $this->getFieldValue($paragraph, $field), 2);
//      $templateProcessor->replaceBlock()
    }
  }

  protected function getTemplateFields(TemplateProcessor $templateDocument) {
    $variables = $templateDocument->getVariables();
    $fields = [];
    foreach ($variables as $variable) {
      if (strpos($variable, '.') !== FALSE) {
        $explode = explode('.', $variable);
        $fields[$explode[0]][$variable] = $explode[1];
      }
      else {
        $fields[$variable] = $variable;
      }
    }

    return $fields;
  }

  protected function stripTagsContent($text, $tags = '', $invert = FALSE) {
    preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
    $tags = array_unique($tags[1]);

    if(is_array($tags) AND count($tags) > 0) {
      if($invert == FALSE) {
        return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
      }
      else {
        return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
      }
    }
    elseif($invert == FALSE) {
      return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
    }
    return $text;
  }

}