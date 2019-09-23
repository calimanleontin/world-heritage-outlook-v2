<?php

namespace Drupal\iucn_assessment\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Custom implementation of the EntityChangedConstraint.
 */
class IucnAssessmentEntityChangedConstraint extends Constraint {

  public $message = 'The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.';

}
