<?php

namespace Drupal\raven\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;

/**
 * Implements a Raven Config form.
 */
class RavenConfigForm {

  /**
   * Builds Raven config form.
   */
  public static function buildForm(array &$form) {
    $config = \Drupal::configFactory()->getEditable('raven.settings');
    $form['client_key'] = [
      '#type'          => 'textfield',
      '#title'         => t('Sentry DSN'),
      '#default_value' => $config->get('client_key'),
      '#description'   => t('Sentry client key for current site.'),
    ];
    $form['public_dsn'] = [
      '#type'          => 'textfield',
      '#title'         => t('Sentry public DSN'),
      '#default_value' => $config->get('public_dsn'),
      '#description'   => t('Sentry public client key for current site.'),
    ];
    $form['environment'] = [
      '#type'          => 'textfield',
      '#title'         => t('Environment'),
      '#default_value' => $config->get('environment'),
      '#description'   => t('The environment in which this site is running (leave blank to use kernel.environment parameter).'),
    ];
    $form['release'] = [
      '#type'          => 'textfield',
      '#title'         => t('Release'),
      '#default_value' => $config->get('release'),
      '#description'   => t('The release this site is running (could be a version or commit hash).'),
    ];
    $form['timeout'] = [
      '#type'          => 'number',
      '#title'         => t('Timeout'),
      '#default_value' => $config->get('timeout'),
      '#description'   => t('Connection timeout in seconds.'),
      '#size'          => 10,
      '#min'           => 0,
    ];
    // "0" is not a valid checkbox option.
    foreach (RfcLogLevel::getLevels() as $key => $value) {
      $log_levels[$key + 1] = $value;
    }
    $form['log_levels'] = [
      '#type'          => 'checkboxes',
      '#title'         => t('Log levels'),
      '#default_value' => $config->get('log_levels'),
      '#description'   => t('Check the log levels that should be captured by Sentry.'),
      '#options'       => $log_levels,
    ];
    $form['fatal_error_handler'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Enable fatal error handler'),
      '#description'   => t('Check to capture fatal PHP errors.'),
      '#default_value' => $config->get('fatal_error_handler'),
    ];
    $form['fatal_error_handler_memory'] = [
      '#type'          => 'number',
      '#title'         => t('Reserved memory'),
      '#description'   => t('Reserved memory for fatal error handler (KB).'),
      '#default_value' => $config->get('fatal_error_handler_memory'),
      '#size'          => 10,
      '#min'           => 0,
    ];
    $form['javascript_error_handler'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Enable JavaScript error handler'),
      '#description'   => t('Check to capture JavaScript errors (if user has the <a target="_blank" href=":url">send JavaScript errors to Sentry</a> permission).', [
        ':url' => Url::fromRoute('user.admin_permissions', [], ['fragment' => 'module-raven'])->toString(),
      ]),
      '#default_value' => $config->get('javascript_error_handler'),
    ];
    $form['polyfill_promise'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Load Promise polyfill'),
      '#description'   => t('Capturing JavaScript errors on IE &lt;= 11 requires a polyfill for <code>Promise</code>. Enable to load the polyfill from <a href="https://cdn.polyfill.io" rel="noreferrer" target="_blank">https://cdn.polyfill.io</a>.'),
      '#default_value' => $config->get('polyfill_promise'),
    ];
    $form['message_limit'] = [
      '#type'          => 'number',
      '#title'         => t('Message limit'),
      '#default_value' => $config->get('message_limit'),
      '#description'   => t('Log message maximum length in characters.'),
      '#size'          => 10,
      '#min'           => 0,
      '#step'          => 1,
      '#required'      => TRUE,
    ];
    $form['stack'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Enable stacktraces'),
      '#default_value' => $config->get('stack'),
      '#description'   => t('Check to add stacktraces to reports.'),
    ];
    $form['trace'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Reflection tracing in stacktraces'),
      '#default_value' => $config->get('trace'),
      '#description'   => t('Check to enable reflection tracing (function calling arguments) in stacktraces. Warning: This setting allows sensitive data to be logged by Sentry!'),
    ];
    $form['ssl'] = [
      '#type'           => 'radios',
      '#title'          => t('SSL Verification'),
      '#default_value'  => $config->get('ssl'),
      '#options'        => [
        'verify_ssl'    => t('Verify SSL'),
        'ca_cert'       => t('Verify against a CA certificate'),
        'no_verify_ssl' => t("Don't verify SSL (not recommended)"),
      ],
    ];
    $form['ca_cert'] = [
      '#type'           => 'textfield',
      '#title'          => t('Path to CA certificate'),
      '#default_value'  => $config->get('ca_cert'),
      '#description'    => t('Path to the CA certificate file of the Sentry server specified in the DSN.'),

      // Only visible when 'ssl' set to ca_cert.
      '#states'         => [
        'visible'       => [
          ':input[name=ssl]' => ['value' => 'ca_cert'],
        ],
      ],
    ];
    $form['ignored_channels'] = [
      '#type'          => 'textarea',
      '#title'         => t('Ignored channels'),
      '#description'   => t('A list of log channels for which messages will not be sent to Sentry (one channel per line). Commonly-configured log channels include <em>access denied</em> for 403 errors and <em>page not found</em> for 404 errors.'),
      '#default_value' => implode("\n", $config->get('ignored_channels') ?: []),
    ];
    $form['#submit'][] = 'Drupal\raven\Form\RavenConfigForm::submitForm';
  }

  /**
   * Submits Raven config form.
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('raven.settings')
      ->set('client_key', $form_state->getValue('client_key'))
      ->set('environment', $form_state->getValue('environment'))
      ->set('release', $form_state->getValue('release'))
      ->set('fatal_error_handler', $form_state->getValue('fatal_error_handler'))
      ->set('fatal_error_handler_memory', $form_state->getValue('fatal_error_handler_memory'))
      ->set('log_levels', $form_state->getValue('log_levels'))
      ->set('stack', $form_state->getValue('stack'))
      ->set('timeout', $form_state->getValue('timeout'))
      ->set('message_limit', $form_state->getValue('message_limit'))
      ->set('trace', $form_state->getValue('trace'))
      ->set('ssl', $form_state->getValue('ssl'))
      ->set('ca_cert', $form_state->getValue('ca_cert'))
      ->set('javascript_error_handler', $form_state->getValue('javascript_error_handler'))
      ->set('public_dsn', $form_state->getValue('public_dsn'))
      ->set('ignored_channels', array_map('trim', preg_split('/\R/', $form_state->getValue('ignored_channels'), -1, PREG_SPLIT_NO_EMPTY)))
      ->set('polyfill_promise', $form_state->getValue('polyfill_promise'))
      ->save();
  }

}
