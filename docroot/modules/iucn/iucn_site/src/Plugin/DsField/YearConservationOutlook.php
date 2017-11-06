<?php

namespace Drupal\iucn_site\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Plugin that renders the author of a node.
 *
 * @DsField(
 *   id = "assessments_conservation_outlook",
 *   title = @Translation("Year conservation outlook"),
 *   entity_type = "node"
 * )
 */

class YearConservationOutlook extends DsFieldBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    global $_iucn_assessment_is_latest_assessment;
    $element = [];
    if ($_iucn_assessment_is_latest_assessment) {
      /* @var $node \Drupal\node\NodeInterface */
      $node = $this->entity();

      $element = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => []],
        '#value' => t('%year Conservation outlook', [
          '%year' => iucn_pdf_assessment_year_display($node),
        ]),
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    if ($this->bundle() != 'site') {
      return FALSE;
    }
    return parent::isAllowed();
  }
}
