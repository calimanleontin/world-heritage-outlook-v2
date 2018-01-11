<?php

namespace Drupal\iucn_site\Plugin\DsField;

use Drupal\ds\Plugin\DsField\DsFieldBase;
use Drupal\Core\Link;

/**
 * Plugin that renders the header on all pages.
 *
 * @DsField(
 *   id = "assessments_pdf_header",
 *   title = @Translation("Pdf Header"),
 *   entity_type = "node"
 * )
 */
class Header extends DsFieldBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    global $base_url;
    /* @var $node \Drupal\node\NodeInterface */
    $node = $this->entity();
    $assessment_year = iucn_pdf_assessment_year_display($node);
    $element = [
      '#type' => 'html_tag',
      '#tag' => 'header',
      '#attributes' => ['class' => []],
      '#value' => '<em>' . t('IUCN World Heritage Outlook: @link', [
        '@link' => Link::createFromRoute($base_url, '<front>')->toString(),
      ]) . '<br/>' .
      $node->getTitle() . ' - ' . iucn_pdf_assessment_year_display($node) . ' ' .
      t('Conservation Outlook Assessment  @archived', [
        '@archived' => $assessment_year != \Drupal::service('iucn_assessment.assessments_year')->current() ? '(' . t('archived') . ')' : '',
      ]) . '</em>',
      '#suffix' => '<div class="pdf-header-cover"></div>',
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
