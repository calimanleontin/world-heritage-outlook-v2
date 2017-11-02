<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Plugin implementation of the 'Assessment factor' field formatter..
 *
 * @FieldFormatter(
 *   id = "assessment_factor",
 *   label = @Translation("Assessment factor"),
 *   field_types = {
 *     "entity_reference_revisions",
 *     "entity_reference"
 *   }
 * )
 */
class AssessmentFactorFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      if (empty($item->entity) || empty($item->entity->name)) {
        continue;
      }

      $types = [
        'assessment_benefits_impact_level' => 'Impact level',
        'assessment_benefits_impact_trend' => 'Trend',
      ];

      $value = $item->entity->name->value;
      $term_type = $this->t($types[$item->entity->vid->target_id]);
      $markup = $term_type . ' - ' . $value;
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
    }
    return $element;
  }

}
