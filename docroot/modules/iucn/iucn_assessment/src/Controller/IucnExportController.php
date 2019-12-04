<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\iucn_assessment\Form\ParagraphAsSiteThreatForm;
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
    $year = $node->field_as_cycle->value;
    if (empty($year) || $year < 2017) {
      $year = 2017;
    }

    $template = drupal_get_path('module', 'iucn_assessment') . "/data/export/assessment_export_tpl_$year.docx";
    $templateDocument = new \PhpOffice\PhpWord\TemplateProcessor($template);
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

      $paragraphFieldList = $this->getOrderedParagraphs($paragraphFieldList, $field);

      foreach ($paragraphFieldList as $idx => $fieldItem) {
        $this->writeParagraphToTemplate($templateDocument, $fieldItem->entity, $referencedFields, $idx + 1);
      }

    }

    $name = Html::getClass('assessment-' . $node->getTitle());

    header("Content-Disposition: attachment; filename=$name.docx");
    ob_end_clean();
    $templateDocument->saveAs('php://output');

    exit(1);
  }

  protected function getOrderedParagraphs(EntityReferenceFieldItemListInterface $items, $field) {
    if ($field != 'field_as_references_p') {
      return $items;
    }

    foreach ($items as $idx => $item) {
      $values[$idx] = $item->entity->get('field_reference')->value;
    }
    asort($values);
    $orderedItems = new EntityReferenceFieldItemList($items->getDataDefinition());
    foreach ($values as $idx => $item) {
      $orderedItems->appendItem($items->get($idx)->entity);
    }
    return $orderedItems;
  }

  protected function getFieldValue(FieldableEntityInterface $entity, $field) {
    if ($field == 'downloaded_time') {
      $time = new DrupalDateTime('now', drupal_get_user_timezone());
      $time = $time->format('d/m/Y \a\t H:i:s');
      return $time;
    }

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
      $implodeChar = '; ';
      if ($field == 'group_other_information') {
        // 2 New lines.
        $implodeChar = "<w:br/></w:t><w:br/><w:t><w:br/></w:t><w:br/><w:t>";
      }

      $fieldRender = implode($implodeChar, $childrenRender);
    }
    else {
      $fieldRender = $this->renderer->render($this->entityDisplays[$entity->getEntityTypeId()][$entity->id()][$field]);
      if (is_object($fieldRender)) {
        $fieldRender = $fieldRender->__toString();
      }
      $fieldRender = $this->stripValue($fieldRender);
    }

    return $fieldRender;
  }

  protected function stripValue($value) {
    $value = $this->stripTagsContent($value, '<button>', TRUE);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = strip_tags($value, '<w:br/><b>');
    $value = str_replace('<b>', '</w:t></w:r><w:r><w:rPr><w:sz w:val="20"/><w:b/></w:rPr><w:t xml:space="preserve">', $value);
    $value = str_replace('</b>', '</w:t></w:r><w:r><w:rPr xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"><w:sz w:val="20"/><w:rFonts w:ascii="Calibri" w:h-ansi="Calibri"/><w:color w:val="0000ff"/></w:rPr><w:t>', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = str_replace(' ;', ';', $value);
    $value = str_replace('&nbsp;', ' ', $value);
    $value = str_replace('&amp;nbsp;', ' ', $value);

    return trim($value);
  }

  protected function writeParagraphToTemplate(TemplateProcessor $templateProcessor, ParagraphInterface $paragraph, $fields, $index) {
    foreach ($fields as $templateVariable => $field) {
      if ($field == 'index') {
        $templateProcessor->setValue("$templateVariable#$index", $index);
        continue;
      }

      if ($field == 'as_projects_date') {
        $date = $this->getFieldValue($paragraph, 'field_as_projects_from');
        if (!empty($paragraph->field_as_projects_to->value)) {
          $date .= ' - ' . $this->getFieldValue($paragraph, 'field_as_projects_to');
        }
        $templateProcessor->setValue("$templateVariable#$index", $date);
        continue;
      }

      if ($field == 'other_info') {
        $setNA = '';
        $subcategories = $paragraph->get('field_as_threats_categories')->getValue();
        if (!empty($subcategories)) {
          $subcategories = array_column($subcategories, 'target_id');
          foreach (ParagraphAsSiteThreatForm::SUBCATEGORY_DEPENDENT_FIELDS as $field => $fieldSubcategories) {
            if (!empty(array_intersect($fieldSubcategories, $subcategories)) && empty($paragraph->get($field)->getValue())) {
              $setNA = 'N/A';
              break;
            }
          }
        }

        $templateProcessor->setValue("$templateVariable#$index", $setNA);
        continue;
      }

      $templateProcessor->setValue("$templateVariable#$index", $this->getFieldValue($paragraph, $field));
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
