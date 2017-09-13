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
    if ($node->hasField('field_as_site')) {
      $site = $node->field_as_site->entity;
      if ($site->field_assessments->count()) {
        foreach ($site->field_assessments as $idx => $item) {
          if (empty($item->entity)) {
            continue;
          }
          $value = [
            'url' => Url::fromRoute('iucn_pdf.download', array('entity_id' => $item->entity->id())),
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
    if ($this->bundle() != 'site_assessment') {
      return FALSE;
    }
    return parent::isAllowed();

  }

}
