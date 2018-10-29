<?php

namespace Drupal\iucn_fields\Plugin;

class TermAlterService {

  const ALTERED_TERM_LABELS_BY_YEAR = [
    2023 => [],
    2020 => [
      1414 => 'Changes in traditional ways of life and knowledge systems that result in negative impact',
      1415 => 'Identity/social cohesion/ changes in local population and community that result in negative impact',
      1404 => 'Volcanic activity',
//        1358 => '',
      1345 => 'Law enforcement',
      1333 => 'Management system/plan',
      1334 => 'Effectiveness of management system/plan',
      1338 => 'Staff capacity, training, and development',
    ],
    2017 => [
      1331 => 'Legal framework',
      1341 => 'Tourism and visitation management',
      1384 => 'Hunting (commercial/subsistence)',
      1385 => 'Poaching',
      1411 => 'Ocean acidification',
      1412 => 'Temperature extremes',
    ],
  ];

  const HIDDEN_TERMS_BY_YEAR = [
    2023 => [],
    2020 => [
      0 => 'hidden',
      1 => 'visible',
    ],
    2017 => [],
  ];

  /**
   * Get the term label for an assessment year.
   *
   * @param int $tid
   *   The term id.
   * @param int $year
   *   The assessment cycle.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The term name.
   */
  public function getTermLabelForYear($tid, $year) {
    foreach (self::ALTERED_TERM_LABELS_BY_YEAR as $key => $altered_term_label) {
      if (empty($altered_term_label)) {
        continue;
      }
      if ($key > $year) {
        continue;
      }
      if (!empty($altered_term_label[$tid])) {
        return t($altered_term_label[$tid]);
      }
    }
    return NULL;
  }

  /**
   * Check if a term is hidden for an assessment year.
   *
   * @param int $tid
   *   The term id.
   * @param int $year
   *   The assessment cycle.
   *
   * @return bool
   *   True if the term is not available.
   */
  public function isTermHiddenForYear($tid, $year) {
    foreach (self::HIDDEN_TERMS_BY_YEAR as $key => $hidden_terms) {
      if (empty($hidden_terms)) {
        continue;
      }
      if ($key > $year) {
        continue;
      }
      if (!empty($hidden_terms[$tid])) {
        return $hidden_terms[$tid] == 'hidden' ? TRUE : FALSE;
      }
    }
    return FALSE;
  }

}
