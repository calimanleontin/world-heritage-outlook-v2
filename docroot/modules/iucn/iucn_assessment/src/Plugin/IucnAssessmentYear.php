<?php

namespace Drupal\iucn_assessment\Plugin;

class IucnAssessmentYear {

  /**
   * Returns current assessment year.
   */
  public function current() {
    return \Drupal::config('iucn_who.settings')->get('assessment_year');
  }

  /**
   * Returns 2014 year.
   */
  public function first() {
    return 2014;
  }

}