<?php

namespace Drupal\iucn_site\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin that renders the author of a node.
 *
 * @DsField(
 *   id = "assessments_notice",
 *   title = @Translation("Latest assessment notice"),
 *   entity_type = "node"
 * )
 */
class LatestAssessmentNotice extends DsFieldBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    global $base_url, $_iucn_assessment_is_latest_assessment;
    $elements = [];
    if (empty($_iucn_assessment_is_latest_assessment)) {
      /* @var $node \Drupal\node\NodeInterface */
      $node = $this->entity();
      $latest_url = Url::fromRoute('iucn_pdf.download', ['entity_id' => $node->id()]);
      $latest_url->setAbsolute(TRUE);

      $assessment_year = iucn_pdf_assessment_year_display($node);

      $elements[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => []],
        '#value' => t('IUCN Conservation Outlook Assessment @year <strong>(@archived)</strong>', [
          '@year' => $assessment_year,
          '@archived' => t('archived'),
        ]),
      ];

      if ($finalised_date = iucn_pdf_assessment_finalised_display($node)) {
        $date = \DateTime::createFromFormat('Y-m-d', $finalised_date);
        $finalised = $date->format('d F Y');
        $elements[] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => []],
          '#value' => t('Finalised on @finalised', [
            '@finalised' => $finalised,
          ]),
        ];
      }

      $elements[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['latest-assessment-notice']],
        '#value' => t('Please note: this is an archived Conservation Outlook Assessment for @site. To access the most up-to-date Conservation Outlook Assessment for this site, please visit @link.', [
          '@site' => $node->getTitle(),
          '@link' => Link::fromTextAndUrl($base_url, $latest_url)->toString(),
        ]),
      ];
    }
    return $elements;
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
