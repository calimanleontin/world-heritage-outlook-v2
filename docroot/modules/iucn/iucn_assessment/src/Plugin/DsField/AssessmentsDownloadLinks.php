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

    $element = [];
    $links = [];

    $print_pdf = \Drupal::service('iucn_pdf.print_pdf');
    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($node->hasField('field_assessments')) {
      if ($node->field_assessments->count()) {
        foreach ($node->field_assessments as $idx => $item) {
          if (empty($item->entity) || !$item->entity->isPublished()) {
            continue;
          }
          //TODO quick fix - removed access check
          /* if (!$item->entity->access('view')) {
            continue;
          }*/
          $value = [
            'url' => Url::fromRoute('iucn_pdf.download', array('entity_id' => $node->id()), ['query'=>['year' => $item->entity->field_as_cycle->value]]),
//            'url' => $node->toUrl()->setOption('query', ['year' => $item->entity->field_as_cycle->value]),
            'title' => $this->t('@year Conservation Outlook Assessment', [
              '@year' => $item->entity->field_as_cycle->value,
              //'@language' => ($current_language == 'ar' ? t('English') : t(\Drupal::languageManager()->getCurrentLanguage()->getName())),
              ]),
          ];
          $value['attributes']['target'][] = '_blank';
          $value['year'] = $item->entity->field_as_cycle->value + 10;
          $links[] = $value;


          if($arabic_pdf = $print_pdf->uploadedPdf($node->id(), 'ar' , $item->entity->field_as_cycle->value)){
            $value = [
              //'url' => Url::fromRoute('iucn_pdf.download', array('entity_id' => $node->id()), ['query'=>['year' => $item->entity->field_as_cycle->value]]),
              'url' => Url::fromRoute('iucn_pdf.download_language',
                [
                  'entity_id' => $node->id(),
                  'language' => 'ar',
                  ],
                ['query' => [
                  'year' => $item->entity->field_as_cycle->value,
                  ],
                ]),
              'title' => $this->t('@year Conservation Outlook Assessment (@language)', [
                '@year' => $item->entity->field_as_cycle->value,
                '@language' => 'Arabic'
                ]),
            ];
            $value['attributes']['target'][] = '_blank';
            $value['year'] = $item->entity->field_as_cycle->value;
            $links[] = $value;
          }
        }
      }
    }

    if (!empty($links)) {
      usort($links,
        function($a, $b) {
          $a_year = $a['year'];
          $b_year = $b['year'];
          return ($a_year < $b_year);
        }
      );
      $element = [
        '#theme' => 'links',
        '#links' => $links,
        '#cache' => [
          'tags' => $node->getCacheTags(),
        ],
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
