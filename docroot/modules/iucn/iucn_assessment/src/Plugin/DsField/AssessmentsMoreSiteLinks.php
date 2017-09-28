<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\Core\Url;
use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Plugin that renders the author of a node.
 *
 * @DsField(
 *   id = "assessments_more_site_links",
 *   title = @Translation("More on this site"),
 *   entity_type = "node"
 * )
 */
class AssessmentsMoreSiteLinks extends DsFieldBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    /* @var $node \Drupal\node\NodeInterface */
    $node = $this->entity();

    $links = [];
    if (!empty($node->field_wdpa_id->value)) {
      $value = [
        'url' => Url::fromUri('http://www.protectedplanet.net/sites/' . $node->field_wdpa_id->value),
        'title' => $this->t('Protected Planet website'),
      ];
      $value['attributes']['target'][] = '_blank';
      $links[] = $value;
    }

    if (!empty($node->field_unesco_id->value)) {
      $value = [
        'url' => Url::fromUri('http://whc.unesco.org/en/list/' . $node->field_unesco_id->value),
        'title' => $this->t('UNESCO World Heritage Centre'),
      ];
      $value['attributes']['target'][] = '_blank';
      $links[] = $value;
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
