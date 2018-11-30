<?php

/**
 * Drush script used to fix referenced values from threat paragraphs.
 *
 * Referenced threat values will be compared to the assessment values
 * on the field_as_values_value field. If a match is found, the referenced
 * value from the threat paragraph will be edited to match the assessment value.
 */
function _iucn_assessment_fix_threat_references() {
  $assessments = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'site_assessment']);
  foreach ($assessments as $assessment) {
    $wh_values = $assessment->get('field_as_values_wh')->getValue();
    $other_values = $assessment->get('field_as_values_bio')->getValue();

    $threats = $assessment->get('field_as_threats_current')->getValue()
      + $assessment->get('field_as_threats_potential')->getValue();

    foreach ($threats as $threat) {
      $threat_paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->loadRevision($threat['target_revision_id']);
      _iucn_assessment_fix_references($threat_paragraph, 'field_as_threats_values_wh', $wh_values, $assessment->id());
      _iucn_assessment_fix_references($threat_paragraph, 'field_as_threats_values_bio', $other_values, $assessment->id());
    }
  }

}

function _iucn_assessment_fix_references(\Drupal\paragraphs\ParagraphInterface $threat, $field, array $values, $assessment_id) {
  $referenced_values = $threat->get($field)->getValue();
  if (!empty($referenced_values)) {
    $update = FALSE;
    foreach ($referenced_values as $referenced_value) {
      $referenced_paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->loadRevision($referenced_value['target_revision_id']);
      foreach ($values as $value) {
        $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->loadRevision($value['target_revision_id']);
        if ($referenced_paragraph->field_as_values_value->value == $paragraph->field_as_values_value->value
          && $referenced_paragraph->getRevisionId() != $paragraph->getRevisionId()) {
          $update = TRUE;
          $referenced_wh_value = $value;
          break;
        }
      }
    }
    if ($update) {
      drush_print("Fixed '{$referenced_paragraph->field_as_values_value->value}' (Assessment id = $assessment_id)");
      $threat->get($field)->setValue($referenced_values);
      $threat->save();
    }
  }
}

_iucn_assessment_fix_threat_references();
