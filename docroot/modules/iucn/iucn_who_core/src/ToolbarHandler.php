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
    return [];
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
