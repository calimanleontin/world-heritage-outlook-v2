<?php

namespace Drupal\raven\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;

/**
 * Implements a Raven Config form.
 */
class RavenConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'raven_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['raven.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['client_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Sentry DSN'),
      '#default_value' => $this->config('raven.settings')->get('client_key'),
      '#description'   => $this->t('Sentry client key for current site.'),
    ];
    $form['public_dsn'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Sentry public DSN'),
      '#default_value' => $this->config('raven.settings')->get('public_dsn'),
      '#description'   => $this->t('Sentry public client key for current site.'),
    ];
    $form['environment'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Environment'),
      '#default_value' => $this->config('raven.settings')->get('environment'),
      '#description'   => $this->t('The environment in which this site is running (leave blank to use kernel.environment parameter).'),
    ];
    $form['release'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Release'),
      '#default_value' => $this->config('raven.settings')->get('release'),
      '#description'   => $this->t('The release this site is running (could be a version or commit hash).'),
    ];
    $form['timeout'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Timeout'),
      '#default_value' => $this->config('raven.settings')->get('timeout'),
      '#description'   => $this->t('Connection timeout in seconds.'),
    ];
    // "0" is not a valid checkbox option.
    foreach (RfcLogLevel::getLevels() as $key => $value) {
      $log_levels[$key + 1] = $value;
    }
    $form['log_levels'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Log levels'),
      '#default_value' => $this->config('raven.settings')->get('log_levels'),
      '#description'   => $this->t('Check the log levels that should be captured by Sentry.'),
      '#options'       => $log_levels,
    ];
    $form['fatal_error_handler'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable fatal error handler'),
      '#description'   => $this->t('Check to capture fatal PHP errors.'),
      '#default_value' => $this->config('raven.settings')->get('fatal_error_handler'),
    ];
    $form['fatal_error_handler_memory'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Reserved memory'),
      '#description'   => $this->t('Reserved memory for fatal error handler (KB).'),
      '#default_value' => $this->config('raven.settings')->get('fatal_error_handler_memory'),
      '#size'          => 10,
    ];
    $form['javascript_error_handler'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable JavaScript error handler'),
      '#description'   => $this->t('Check to capture JavaScript errors (if user has the <a target="_blank" href=":url">send JavaScript errors to Sentry</a> permission).', [
        ':url' => Url::fromRoute('user.admin_permissions', [], ['fragment' => 'module-raven'])->toString(),
      ]),
      '#default_value' => $this->config('raven.settings')->get('javascript_error_handler'),
    ];
    $form['stack'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable stacktraces'),
      '#default_value' => $this->config('raven.settings')->get('stack'),
      '#description'   => $this->t('Check to add stacktraces to reports.'),
    ];
    $form['trace'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Reflection tracing in stacktraces'),
      '#default_value' => $this->config('raven.settings')->get('trace'),
      '#description'   => $this->t('Check to enable reflection tracing (function calling arguments) in stacktraces. Warning: This setting allows sensitive data to be logged by Sentry!'),
    ];
    $form['ssl'] = [
      '#type'           => 'radios',
      '#title'          => $this->t('SSL Verification'),
      '#default_value'  => $this->config('raven.settings')->get('ssl'),
      '#options'        => [
        'verify_ssl'    => $this->t('Verify SSL'),
        'ca_cert'       => $this->t('Verify against a CA certificate'),
        'no_verify_ssl' => $this->t("Don't verify SSL (not recommended)"),
      ],
    ];
    $form['ca_cert'] = [
      '#type'           => 'textfield',
      '#title'          => $this->t('Path to CA certificate'),
      '#default_value'  => $this->config('raven.settings')->get('ca_cert'),
      '#description'    => $this->t('Path to the CA certificate file of the Sentry server specified in the DSN.'),

      // Only visible when 'ssl' set to ca_cert.
      '#states'         => [
        'visible'       => [
          ':input[name=ssl]' => ['value' => 'ca_cert'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('raven.settings')
      ->set('client_key', $form_state->getValue('client_key'))
      ->set('environment', $form_state->getValue('environment'))
      ->set('release', $form_state->getValue('release'))
      ->set('fatal_error_handler', $form_state->getValue('fatal_error_handler'))
      ->set('fatal_error_handler_memory', $form_state->getValue('fatal_error_handler_memory'))
      ->set('log_levels', $form_state->getValue('log_levels'))
      ->set('stack', $form_state->getValue('stack'))
      ->set('timeout', $form_state->getValue('timeout'))
      ->set('trace', $form_state->getValue('trace'))
      ->set('ssl', $form_state->getValue('ssl'))
      ->set('ca_cert', $form_state->getValue('ca_cert'))
      ->set('javascript_error_handler', $form_state->getValue('javascript_error_handler'))
      ->set('public_dsn', $form_state->getValue('public_dsn'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
