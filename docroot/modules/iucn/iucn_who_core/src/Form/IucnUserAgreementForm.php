<?php

namespace Drupal\iucn_who_core\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User Agreement page form.
 */
class IucnUserAgreementForm implements FormInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Holds the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $account;


  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Initialize method.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The current user account.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountProxy $account, LanguageManagerInterface $languageManager, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->account = $account;
    $this->languageManager = $languageManager;
    $this->config = $config_factory->get('iucn_who_core.settings');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_user_agreement_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('iucn_who_core.settings');
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());
    $agreed = !empty($user->field_accepted_agreement->value) || !empty($user->field_user_agreement_disabled->value);
    $data = null;

    $roles = $user->getRoles(TRUE);
    foreach ($roles as $role) {
      $enabled = $config->get(sprintf('agreement.%s.enabled', $role));
      $content = $config->get(sprintf('agreement.%s.content.value', $role));
      if ($enabled && empty($data)) {
        $data = $content;
      }

      if (!$enabled) {
        $agreed = true;
      }
    }

    if (empty($data)) {
      $data = $config->get('agreement.default.content.value');
    }

    $form['agreement'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      'user_agreement_data' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $data,
      ],
      'agree' => [
        '#type' => 'checkbox',
        '#title' => $this->t('@agree_checkbox', ['@agree_checkbox' => $this->t('I agree to this user agreement')]),
        '#default_value' => $agreed,
        '#access' => !$agreed,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#name' => 'submit',
          '#value' => $this->t('@agree_submit', ['@agree_submit' => $this->t('Submit')]),
          '#access' => !$agreed,
        ],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $this->account->id();
    $user = User::load($uid);
    if (empty($user->field_accepted_agreement->value)) {
      $user->set('field_accepted_agreement', date('Y-m-d\TH:i:s', time()));
      $user->save();
    }
    $form_state->setRedirect('who.user-dashboard');
  }

}
