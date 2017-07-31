<?php

namespace Drupal\dropdown_language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for global settings.
 */
class DropdownLanguageSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dropdown_language_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dropdown_language.setting',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dropdown_language.setting');

    $form['display_language_id'] = [
      '#type' => 'radios',
      '#options' => [
        '1' => $this->t('Show Language Name'),
        '0' => $this->t('Show Language ID'),
        '2' => $this->t('Use Custom Labels for Language Names (per block instance)'),
      ],
      '#title' => $this->t('Display Language Labelling'),
      '#default_value' => $config->get('display_language_id'),
    ];

    $form['decor'] = [
      '#type' => 'details',
      '#title' => $this->t('<q>Switch Language</q> Decor'),
      '#open' => TRUE,
    ];

    $form['decor']['wrapper'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show block with fieldset wrapping'),
      '#default_value' => $config->get('wrapper'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dropdown_language.setting')
      ->set('wrapper', $form_state->getValue('wrapper'))
      ->set('display_language_id', $form_state->getValue('display_language_id'))
      ->save();
    parent::submitForm($form, $form_state);
    // @todo Pass cache tags to module's block to properly invalidate cache.
  }

}
