<?php

/**
 * @file
 * This file contains the block with statistical data shown on the home page.
 */

namespace Drupal\iucn_who_homepage\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use Drupal\iucn_who_core\SiteStatus;

/**
 * @Block(
 *   id = "home_page_report",
 *   admin_label = @Translation("Home page report"),
 * )
 */
class HomePageDocumentBlock extends BlockBase {

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $file = isset($config['file']) ? $config['file'] : '';
    $form['download_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra comment'),
      '#size' => 40,
      '#default_value' => isset($config['download_string']) ? $config['download_string'] : '',
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select report file'),
      '#description' => $this->t('Upload report file to server'),
      '#upload_location' => 'public://uploads/reports',
      '#default_value' => $file,
    ];
    return $form;
  }


  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['file'] = $values['file'];
    $this->configuration['download_string'] = $values['download_string'];
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    // @todo remove line below to allow caching in production
    $content = ['#cache' => ['max-age' => 0]];
    $config = $this->getConfiguration();
    if (!empty($config['file']) && $file = File::load(reset($config['file']))) {
      $content['document_link'] = file_create_url($file->getFileUri());
      if (!empty($config['download_string'])) {
        $content['download_string'] = $this->t($config['download_string']);
      }
    }
    return $content;
  }
}