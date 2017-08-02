<?php

namespace Drupal\anchor_only\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines the "anchor" plugin.
 *
 * @CKEditorPlugin(
 *   id = "anchor_only",
 *   label = @Translation("Anchor only"),
 *   module = "anchor_only"
 * )
 */
class AnchorOnly extends CKEditorPluginBase {

  /**
  * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getFile().
  */
  function getFile() {
    return drupal_get_path('module', 'anchor_only') . '/js/plugins/anchor_only/plugin.js';
  }
  
  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [
      'fakeobjects',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }
  
    /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginButtonsInterface::getButtons().
   */
  function getButtons() {
    return [
      'anchor_only' => [
        'label' => t('Anchor only'),
        'image' => drupal_get_path('module', 'anchor_only') . '/js/plugins/anchor_only/icons/anchor_only.png',
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }
}
