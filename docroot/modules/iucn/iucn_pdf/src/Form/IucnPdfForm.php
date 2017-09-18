<?php

namespace Drupal\iucn_pdf\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures Entity Print settings.
 */
class IucnPdfForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($config_factory);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_pdf_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'iucn_pdf.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iucn_pdf.settings');
    $form['iucn_pdf'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Print PDF Config'),
    ];

    $form['iucn_pdf']['sites_per_cron'] = [
      '#type' => 'select',
      '#title' => $this->t('Sites per cron'),
      '#description' => $this->t('Number of sites generated in pdf during cron call.'),
      '#default_value' => $config->get('sites_per_cron'),
      '#options' => [
        1 => 1,
        2 => 2,
        3 => 3,
        5 => 5,
        10 => 10,
        25 => 25,
        50 => 250,
        100 => 100,
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('iucn_pdf.settings')
      ->set('sites_per_cron', $form_state->getValue('sites_per_cron'))
      ->save();

    parent::submitForm($form, $form_state);

    drupal_set_message($this->t('Configuration saved.'));
  }

}
