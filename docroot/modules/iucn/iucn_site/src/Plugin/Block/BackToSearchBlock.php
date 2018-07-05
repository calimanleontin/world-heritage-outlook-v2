<?php

namespace Drupal\iucn_site\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "iucn_site_back_to_search",
 *   admin_label = @Translation("Back to search button"),
 *   category = @Translation("IUCN Site"),
 * )
 */
class BackToSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#type' => 'inline_template',
      '#template' => sprintf('
      <div class="sites-back-button">
        <a href="#" class="back-button hidden"><i class="back-icon"></i>%s</a>
        <a href="#" class="go-to-button">%s<i class="go-to-icon"></i></a>
      </div>
      ', $this->t('Back to search'), $this->t('Go to search')),
      '#context' => [],
    ];

    /** @var \Drupal\node\Entity\Node $node */
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node) {
      $nodes_views = [
        'site' => 'view.sites_search.sites_search_page_database',
        'publication' => 'view.publications.publications_page_database',
      ];
      if ($node instanceof Node && !empty($nodes_views[$node->bundle()])) {
        $node_urls = [];
        $languages = \Drupal::languageManager()->getLanguages();
        foreach ($languages as $langcode => $language) {
          if ($node->hasTranslation($langcode)) {
            $node_urls[] = $node->url('canonical', ['language' => $language]);
          }
        }

        $view_url = Url::fromRoute($nodes_views[$node->bundle()])->toString();
        $build['#attached']['library'] = ['iucn_site/back-to-search-js'];
        $build['#attached']['drupalSettings']['iucn_site']['go_to_buttons']['view_url'] = $view_url;
        $build['#attached']['drupalSettings']['iucn_site']['go_to_buttons']['node_urls'] = $node_urls;
      }
    }
    return $build;
  }

}
