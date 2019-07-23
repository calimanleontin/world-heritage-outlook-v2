<?php

namespace Drupal\iucn_fields\Plugin;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;

class TermAlterService {

  /** @var \Drupal\Core\Routing\RouteMatchInterface */
  protected $routeMatch;

  /** @var array */
  protected $alteredTerms;

  public function __construct(RouteMatchInterface $routeMatch) {
    $this->routeMatch = $routeMatch;
    $this->alteredTerms = Yaml::decode(file_get_contents(__DIR__ . '../../../iucn_fields.altered_terms.yml'));
  }

  /**
   * Get the term label for an assessment year.
   *
   * @param TermInterface $term
   *   The term.
   * @param int $year
   *   The assessment cycle.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   The term name.
   */
  public function getTermLabelForYear(TermInterface $term, $year) {
    if (empty($this->alteredTerms)) {
      return NULL;
    }
    foreach ($this->alteredTerms as $key => $alteredTermLabel) {
      if (empty($alteredTermLabel)) {
        continue;
      }
      if ($key > $year) {
        continue;
      }
      if (!empty($alteredTermLabel[$term->id()])) {
        return $alteredTermLabel[$term->id()] != '<hidden>' ? t($alteredTermLabel[$term->id()]) : '<hidden>';
      }
    }
    return NULL;
  }

  /**
   * Check if a term is hidden for an assessment year.
   *
   * @param TermInterface $term
   *   The term.
   * @param int $year
   *   The assessment cycle.
   *
   * @return bool
   *   True if the term is not available.
   */
  public function isTermHiddenForYear(TermInterface $term, $year) {
    return self::getTermLabelForYear($term, $year) == '<hidden>';
  }

  /**
   * Retrieves termIds hidden for a given cycle
   *
   * @param $cycle
   *
   * @return array
   */
  public function getHiddenTermsForCycle($cycle) {
    if (empty($this->alteredTerms)) {
      return [];
    }

    if (empty($this->alteredTerms[$cycle])) {
      return [];
    }

    return array_keys(array_filter($this->alteredTerms[$cycle], function ($value) {
      return $value == '<hidden>';
    }));
  }

}
