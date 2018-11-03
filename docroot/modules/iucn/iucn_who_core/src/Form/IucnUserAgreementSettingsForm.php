<?php

namespace Drupal\iucn_who_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserAgreementSettingsForm.
 *
 * @package Drupal\iucn_who_core\Form
 */
class IucnUserAgreementSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Holds the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\system\CustomFieldFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactory $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'user_agreement.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_agreement_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('user_agreement.settings');
    $nid = $config->get('user_agreement_node');
    $form['user_agreement'] = [
      '#type' => 'fieldset',
      'user_agreement_node' => [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => [
          'target_bundles' => ['page'],
        ],
        '#title' => $this->t('User agreement node'),
        '#description' => $this->t('Node <em>title</em> of the page where your User Agreement are published.'),
        '#default_value' => !empty($nid) ? $this->entityTypeManager->getStorage('node')->load($config->get('user_agreement_node')) : NULL,
      ],
      'user_agreement_label_checkbox' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label for the checkbox'),
        '#description' => $this->t('Type here something like "By clicking Confirm button I agree to User Agreement.".'),
        '#default_value' => $config->get('user_agreement_label_checkbox'),
      ],
      'user_agreement_label_button' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label for the submit button'),
        '#description' => $this->t('Type here the title for submit button.'),
        '#default_value' => $config->get('user_agreement_label_button'),
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('user_agreement.settings')
      ->set('user_agreement_node', $form_state->getValue('user_agreement_node'))
      ->set('user_agreement_label_button', $form_state->getValue('user_agreement_label_button'))
      ->set('user_agreement_label_checkbox', $form_state->getValue('user_agreement_label_checkbox'))
      ->save();
  }

}
