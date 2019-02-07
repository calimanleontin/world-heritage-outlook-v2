<?php

namespace Drupal\iucn_fields\Plugin;

use Drupal\Component\Serialization\Yaml;

class TermAlterService {

  /**
   * Get the term label for an assessment year.
   *
   * @param int $tid
   *   The term id.
   * @param int $year
   *   The assessment cycle.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   The term name.
   */
  public function getTermLabelForYear($tid, $year) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    $altered_terms_by_year = Yaml::decode(file_get_contents(__DIR__ . '../../../iucn_fields.altered_terms.yml'));
    foreach ($altered_terms_by_year as $key => $altered_term_label) {
      if (empty($altered_term_label)) {
        continue;
      }
      if ($key > $year) {
        continue;
      }
      if (!empty($altered_term_label[$tid])) {
        return $altered_term_label[$tid] != '<hidden>' ? t($altered_term_label[$tid]) : '<hidden>';
      }
    }

    $terms = [1330, 1332, 1333];
    if (
      ($route_name == 'iucn_assessment.modal_paragraph_add') ||
      ($route_name == 'iucn_assessment.modal_paragraph_edit') ||
      ($route_name == 'entity.node.edit_form')
    ) {
      if (in_array($tid, $terms)) {
        $term = \Drupal\taxonomy\Entity\Term::load($tid);
        return $term->getName() . ' ' . strip_tags($term->getDescription());
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
    return self::getTermLabelForYear($tid, $year) == '<hidden>';
  }

}
