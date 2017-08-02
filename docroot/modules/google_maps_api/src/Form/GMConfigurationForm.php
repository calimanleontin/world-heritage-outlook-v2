<?php

namespace Drupal\google_maps_api\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


class GMConfigurationForm extends ConfigFormBase {

  const CONFIG_NAME = 'google_maps_api.settings';
  const API_KEY = 'api_key';

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [ self::CONFIG_NAME, ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'google_maps_api_config_form';
  }

  /**
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form[self::API_KEY] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter your secret key. You can obtain one <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">here</a>.'),
      '#default_value' => \Drupal::state()->get(self::CONFIG_NAME),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set(self::CONFIG_NAME, $form_state->getValue(self::API_KEY));
    parent::submitForm($form, $form_state);
  }
}
