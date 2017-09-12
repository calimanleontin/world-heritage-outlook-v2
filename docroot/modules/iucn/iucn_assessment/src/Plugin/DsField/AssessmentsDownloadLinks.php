<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\Core\Url;
use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Plugin that renders the author of a node.
 *
 * @DsField(
 *   id = "assessments_download_links",
 *   title = @Translation("Download PDF"),
 *   entity_type = "node"
 * )
 */
class AssessmentsDownloadLinks extends DsFieldBase {

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
          $value = [
            'url' => $node->toUrl()->setOption('query', ['year' => $item->entity->field_as_cycle->value]),
            'title' => $this->t('Site Assessment @year', ['@year' => $item->entity->field_as_cycle->value]),
          ];
          $value['attributes']['target'][] = '_blank';
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
