<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnExportController extends ControllerBase {

  /**
   * @var array
   *
   * The builds for parsed entities.
   */
  protected $entityDisplays;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(RendererInterface $renderer) {
    $this->entityTypeManager = $this->entityTypeManager();
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

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

      if ($firstParagraphField == 'field_as_values_wh.index' || $firstParagraphField == 'field_as_values_bio.index') {
        $templateDocument->cloneRow($firstParagraphField, count($paragraphFieldList));
      }

      foreach ($paragraphFieldList as $idx => $fieldItem) {
        $this->writeParagraphToTemplate($templateDocument, $fieldItem->entity, $referencedFields, $idx + 1);
      }

    }

    $name = Html::getClass($node->getTitle());

    header("Content-Disposition: attachment; filename=$name.docx");
    ob_end_clean();
    $templateDocument->saveAs('php://output');

    exit(1);
  }

  protected function getFieldValue(FieldableEntityInterface $entity, $field) {
    /** @var \Drupal\Core\Entity\EntityViewBuilder $viewBuilder */
    $viewBuilder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());

    if (empty($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()])) {
      $this->entityDisplays[$entity->getEntityTypeId()][$entity->id()] = $viewBuilder->build($viewBuilder->view($entity, 'doc'));
    }

    if (empty($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()][$field])
      && empty($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()]['#fieldgroups'][$field])) {
      return '';
    }

    if (strpos($field, "group_") === 0) {
      $children = $this->entityDisplays[$entity->getEntityTypeId()][$entity->id()]['#fieldgroups'][$field]->children;
      $childrenRender = [];
      foreach ($children as $child) {
        $childRender = $this->renderer->render($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()][$child]);
        if (is_object($childRender)) {
          $childRender = $childRender->__toString();
        }

        $childRender = $this->stripValue($childRender);
        if (empty($childRender)) {
          continue;
        }

        $childrenRender[] = $childRender;
      }
      $fieldRender = implode(', ', $childrenRender);
    }
    else {
      $fieldRender = $this->renderer->render($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()][$field]);
      if (is_object($fieldRender)) {
        $childRender = $fieldRender->__toString();
      }
      $fieldRender = $this->stripValue($fieldRender);
    }

    return $fieldRender;
  }

  protected function stripValue($value) {
    $value = $this->stripTagsContent($value, '<button>', TRUE);
    return trim(strip_tags($value));
  }

  protected function writeParagraphToTemplate(TemplateProcessor $templateProcessor, ParagraphInterface $paragraph, $fields, $index) {
    foreach ($fields as $templateVariable => $field) {
      if ($field == 'index') {
        $templateProcessor->setValue("$templateVariable#$index", $index);
        continue;
      }

      $templateProcessor->setValue("$templateVariable#$index", $this->getFieldValue($paragraph, $field), 2);
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
