<?php

namespace Drupal\iucn_who_core;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Toolbar integration handler.
 */
class ToolbarHandler implements ContainerInjectionInterface {

  use StringTranslationTrait;


  /**
   * ToolbarHandler constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree, AccountProxyInterface $account) {
    $this->menuLinkTree = $menu_link_tree;
    $this->account = $account;
  }


  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('current_user')
    );
  }


  /**
   * Hook bridge.
   *
   * @return array
   *   The toolbar items render array.
   *
   * @see hook_toolbar()
   */
  public function toolbar() {
    $items['iucn_who'] = [
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];

    if ($this->account->hasPermission('view the administration theme')) {
      $items['iucn_who'] += [
        '#type' => 'toolbar_item',
        '#weight' => 1000,
        'tab' => [
          '#type' => 'link',
          '#title' => $this->t('WHO'),
          '#url' => Url::fromRoute('system.admin'),
          '#attributes' => [
            'title' => $this->t('WHO tasks'),
            'class' => ['toolbar-icon', 'toolbar-icon-system-admin-config'],
          ],
        ],
        'tray' => [
          '#heading' => $this->t('WHO menu'),
          'iucn_who_menu' => [
            '#lazy_builder' => [ToolbarHandler::class . ':lazyBuilder', []],
            // Force the creation of the placeholder instead of rely on the
            // automatical placeholdering or otherwise the page results
            // uncacheable when max-age 0 is bubbled up.
            '#create_placeholder' => TRUE,
          ],
        ],
      ];
    }

    $items['who.user-dashboard']=  [
      '#type' => 'toolbar_item',
      '#weight' => 1001,
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
      'tab' => [
        '#type' => 'link',
        '#title' => $this->t('Dashboard'),
        '#url' => Url::fromRoute('who.user-dashboard'),
        '#attributes' => [
          'class' => ['toolbar-icon', 'toolbar-icon-system-admin-reports'],
        ],
      ],
    ];
    return $items;
  }


  /**
   * Lazy builder callback for the menu toolbar.
   *
   * @return array
   *   The renderable array rapresentation of the menu.
   */
  public function lazyBuilder() {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setTopLevelOnly();
    $tree = $this->menuLinkTree->load('who', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $build = $this->menuLinkTree->build($tree);
    // CacheableMetadata::createFromRenderArray($build)
    // ->addCacheableDependency($this->config)->applyTo($build);
    return $build;
  }
}