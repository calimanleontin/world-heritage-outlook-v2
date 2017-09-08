<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Plugin that renders the author of a node.
 *
 * @DsField(
 *   id = "assessments_links",
 *   title = @Translation("Assessments links"),
 *   entity_type = "node"
 * )
 */
class AssessmentsLinks extends DsFieldBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    /* @var $node \Drupal\node\NodeInterface */
    $node = $this->entity();


    $links = [];
    if ($node->hasField('field_assessments')) {
      if ($node->field_assessments->count()) {
        foreach ($node->field_assessments as $idx => $item) {
          if (empty($item->entity)) {
            continue;
          }
          $active = iucn_assessment_year_display($node);
          $value = [
            'url' => $node->toUrl()->setOption('query', ['year' => $item->entity->field_as_cycle->value])->toUriString(),
            'title' => $item->entity->field_as_cycle->value,
          ];
          if ($item->entity->field_as_cycle->value == $active) {
            $value['attributes']['class'][] = 'active-year';
          }
          $links[] = $value;
        }
      }
    }

    return [
      '#theme' => 'links',
      '#links' => $links,
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];

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
