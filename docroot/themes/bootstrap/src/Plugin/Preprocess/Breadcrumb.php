<?php
/**
 * @file
 * Contains \Drupal\bootstrap\Plugin\Preprocess\Breadcrumb.
 */

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Annotation\BootstrapPreprocess;
use Drupal\bootstrap\Utility\Variables;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Pre-processes variables for the "breadcrumb" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("breadcrumb")
 */
class Breadcrumb extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    $breadcrumb = &$variables['breadcrumb'];

    // Determine if breadcrumbs should be displayed.
    $breadcrumb_visibility = $this->theme->getSetting('breadcrumb');
    if (($breadcrumb_visibility == 0 || ($breadcrumb_visibility == 2 && \Drupal::service('router.admin_context')->isAdminRoute())) || empty($breadcrumb)) {
      $breadcrumb = [];
      return;
    }

    // Remove first occurrence of the "Home" <front> link, provided by core.
    if (!$this->theme->getSetting('breadcrumb_home')) {
      $front = Url::fromRoute('<front>')->toString();
      foreach ($breadcrumb as $key => $link) {
        if (isset($link['url']) && $link['url'] === $front) {
          unset($breadcrumb[$key]);
          break;
        }
      }
    }

    if ($this->theme->getSetting('breadcrumb_title') && !empty($breadcrumb)) {



      $menu_items = iucn_who_get_all_menu('main');

      $request = \Drupal::request();
      $route_match = \Drupal::routeMatch();

      $parameters = $route_match->getParameters()->all();
      if (!empty($parameters['node']) && ($parameters['node'] instanceof NodeInterface)) {
        $node = $parameters['node'];
        /** @var \Drupal\node\Entity\Node $node */
        $current_path = $node->url();
        if (array_key_exists($current_path, $menu_items)) {
          $page_title = $menu_items[$current_path];
        }
        else {
          $page_title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
        }
      }
      else {

        $currentLanguage = '';
        $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
        $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        if ($default_language != $current_language) {
          $currentLanguage = "/$current_language";
        }

        $current_path = $currentLanguage . $route_match->getRouteObject()->getPath();
        if (array_key_exists($current_path, $menu_items)) {
          $page_title = $menu_items[$current_path];
        }
        else {
          $page_title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
        }
      }

      if (!empty($page_title)) {
        $breadcrumb[] = [
          'text' => $page_title,
          'attributes' => new Attribute(['class' => ['active']]),
        ];
      }
    }

    // Add cache context based on url.
    $variables->addCacheContexts(['url']);
  }

}
