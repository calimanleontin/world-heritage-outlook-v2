<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\Core\Url;
use Drupal\ds\Plugin\DsField\DsFieldBase;

/**
 * Plugin that renders the download pdf links of a site in all languages.
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

    $element = [
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];

    if (!$node->hasField('field_assessments')) {
      return $element;
    }

    if ($node->get('field_assessments')->isEmpty()) {
      return $element;
    }

    /** @var \Drupal\iucn_pdf\PrintPdf $printPdf */
    $printPdf = \Drupal::service('iucn_pdf.print_pdf');

    /** @var \Drupal\Core\Language\Language[] $languages */
    $languages = \Drupal::languageManager()->getLanguages();

    /** @var \Drupal\node\Entity\Node[] $assessments */
    $assessments = $node->get('field_assessments')->referencedEntities();
    rsort($assessments);

    $links = [];
    foreach ($assessments as $assessment) {
      if (!$assessment->isPublished()) {
        continue;
      }

      if (!$assessment->access('view')) {
        continue;
      }

      $cycle = $assessment->get('field_as_cycle')->value;

      foreach ($languages as $language) {
        if (!$assessment->hasTranslation($language->getId())) {
          continue;
        }

        $url = Url::fromRoute('iucn_pdf.download', [
          'entity_id' => $node->id(),
        ], [
          'language' => $language,
          'query' => ['year' => $cycle,],
          ]);

        $link = [
          'url' => $url,
          'title' => $this->t('@year Conservation Outlook Assessment (@language)', [
            '@year' => $cycle,
            '@language' => strtoupper($language->getId()),
          ]),
          'attributes' => [
            'target' => '_blank'
          ]
        ];

        if ($uploadedPdf = $printPdf->uploadedPdf($node->id(), $language->getId(), $cycle)) {
          $link = [
            'url' => Url::fromRoute('iucn_pdf.download_language', [
              'entity_id' => $node->id(),
              'language' => 'ar',
            ], [
              'query' => [
                'year' => $cycle,
              ],
            ]),
            'title' => $this->t('@year Conservation Outlook Assessment (@language)', [
              '@year' => $cycle,
              '@language' => strtoupper($language->getId()),
            ]),
          ];
        }
        $link['attributes']['target'] = '_blank';
        $links[] = $link;
      }
    }

    $element += [
      '#theme' => 'links',
      '#links' => $links,
    ];

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
