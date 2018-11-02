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
    $this->config = $config_factory->get('user_agreement.settings');
    $this->entityTypeManager = $entity_type_manager;
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

    $config = \Drupal::config('user_agreement.settings');
    $nid = $config->get('user_agreement_node');
    $agreed = FALSE;
    $data = '';
    if ($nid) {
      $current_user = \Drupal::currentUser();
      $uid = $current_user->id();
      $user = User::load($uid);
      if (!empty($user->field_agreement_accepted->value)) {
        $agreed = TRUE;
      }

      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!empty($node) && !empty($node->field_page_elements[0]->target_id)) {
        $p = Paragraph::load($node->field_page_elements[0]->target_id);
        $data = $p->field_content->value;
      }
    }
    $agree_checkbox = $config->get('user_agreement_label_checkbox');
    $agree_submit = $config->get('user_agreement_label_button');

    $form['agreement'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      'user_agreement_data' => [
        '#type'          => 'textarea',
        '#title'         => t('User Agreement'),
        '#default_value' => PlainTextOutput::renderFromHtml($data),
        '#value'         => PlainTextOutput::renderFromHtml($data),
        '#rows'          => 10,
        '#weight'        => 0,
        '#attributes'    => array('readonly' => 'readonly'),
      ],
      'agree' => [
        '#type' => 'checkbox',
        '#title' => $this->t('@agree_checkbox', ['@agree_checkbox' => $agree_checkbox]),
        '#default_value' => $agreed,
        '#access' => !$agreed,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#name' => 'submit',
          '#value' => $this->t('@agree_submit', ['@agree_submit' => $agree_submit]),
          '#access' => !$agreed,
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::config('user_agreement.settings');
    $nid = $config->get('user_agreement_node');
    if ($nid) {
      $uid = $this->account->id();
      $user = User::load($uid);
      if (empty($user->field_agreement_accepted->value)) {
        $user->set('field_agreement_accepted', time());
        $user->save();
      }
    }
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

}
