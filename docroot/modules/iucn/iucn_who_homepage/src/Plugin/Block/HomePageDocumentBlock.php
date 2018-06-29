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

  const CONFIG_KEY_FILE = 'iucn_who_homepage.document_block.file';
  const CONFIG_KEY_TITLE = 'iucn_who_homepage.document_block.title';
  const CONFIG_KEY_SUBTITLE = 'iucn_who_homepage.document_block.subtitle';

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $title = \Drupal::state()->get(self::CONFIG_KEY_TITLE);
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#description' => 'Title in <b>english</b> ex. Download the report',
      '#size' => 40,
      '#default_value' => $title,
    ];

    $subtitle = \Drupal::state()->get(self::CONFIG_KEY_SUBTITLE);
    $form['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtitle'),
      '#required' => TRUE,
      '#description' => 'Subtitle in <b>english</b> ex. IUCN World Heritage Outlook 2017',
      '#size' => 40,
      '#default_value' => $subtitle,
    ];

    $languages = \Drupal::languageManager()->getLanguages();
    foreach($languages as $language) {
      $fid = \Drupal::state()->get(self::CONFIG_KEY_FILE . '.' . $language->getId());
      $form['file_' . $language->getId()] = [
        '#type' => 'managed_file',
        '#title' => 'Select report file for ' . $language->getName(),
        '#required' => $language->getId() == 'en',
        '#description' => $this->t('Upload report file to server'),
        '#upload_location' => 'public://uploads/reports',
        '#default_value' => ['fid' => $fid],
      ];
    }
    return $form;
  }


  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    if ($title = $values['title']) {
      \Drupal::state()->set(self::CONFIG_KEY_TITLE, $title);
    }
    if ($subtitle = $values['subtitle']) {
      \Drupal::state()->set(self::CONFIG_KEY_SUBTITLE, $subtitle);
    }
    $languages = \Drupal::languageManager()->getLanguages();
    foreach($languages as $language) {
      $fid = $values['file_' . $language->getId()][0];
      if (!empty($fid)) {
        \Drupal::state()
          ->set(self::CONFIG_KEY_FILE . '.' . $language->getId(), $fid);
      }
      else {
        \Drupal::state()
          ->delete(self::CONFIG_KEY_FILE . '.' . $language->getId());
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [];
    $language = \Drupal::languageManager()->getCurrentLanguage();
    if (!$fid = \Drupal::state()->get(self::CONFIG_KEY_FILE . '.' . $language->getId())) {
      $fid = \Drupal::state()->get(self::CONFIG_KEY_FILE . '.en');
    }
    if (!empty($fid) && $file = File::load($fid)) {
      $content['output'] = [
        '#theme' => 'homepage_report',
        '#title' => \Drupal::state()->get(self::CONFIG_KEY_TITLE),
        '#subtitle' => \Drupal::state()->get(self::CONFIG_KEY_SUBTITLE),
        '#file_url' => file_create_url($file->getFileUri()),
      ];
    }
    return $content;
  }
}
