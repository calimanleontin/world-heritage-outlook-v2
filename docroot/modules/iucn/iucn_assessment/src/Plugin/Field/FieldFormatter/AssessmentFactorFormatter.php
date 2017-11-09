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

    $types = [
      'assessment_benefits_impact_level' => $this->t('Impact level'),
      'assessment_benefits_impact_trend' => $this->t('Trend'),
    ];

    $fields = [
      'field_as_benefits_commun_in' => $this->t('Community within site'),
      'field_as_benefits_commun_out' => $this->t('Community outside site'),
      'field_as_benefits_commun_wide' => $this->t('Wider Community (global)'),
    ];

    foreach ($items as $delta => $item) {
      if (empty($item->entity) || empty($item->entity->name)) {
        continue;
      }
      $term_type = NULL;
      if (isset($types[$item->entity->vid->target_id])) {
        $term_type = $types[$item->entity->vid->target_id];
      }
      else {
        /** @var FieldItemListInterface $item*/
        $field_name = $item->getFieldDefinition()->getName();
        if (isset($fields[$field_name])) {
          $term_type = $fields[$field_name];
        }
      }
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => ($term_type ? $term_type . ' - ' : '') . $item->entity->name->value,
      ];
    }

    return $element;
  }

}
