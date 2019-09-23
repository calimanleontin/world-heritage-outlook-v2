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

  const IGNORED_ROLES = [
    'anonymous',
    'authenticated',
  ];

  const MAIN_ROLES = [
    'iucn_manager',
    'assessor',
    'coordinator',
    'reviewer',
  ];

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
      'iucn_who_core.settings',
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
    $config = $this->config('iucn_who_core.settings');
    $defaultContent = $config->get('agreement.default.content.value');

    $form['user_agreement_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('User agreements per role'),
    ];

    $form['user_agreement']['default'] = [
      '#type' => 'details',
      '#title' => $this->t('Default user agreement settings'),
      '#open' => TRUE,
      '#description' => $this->t('Default user agreement text if a specific text is not set on role.'),
      '#group' => 'user_agreement_tabs',
    ];

    $form['user_agreement']['default']['user_agreement_content_default'] = [
      '#type' => 'text_format',
      '#title' => $this->t('User agreement default content'),
      '#format' => 'html',
      '#default_value' => $defaultContent ?: '',
    ];

    $allRoles = array_keys($this->entityTypeManager->getStorage('user_role')->loadMultiple());

    $this->appendRolesToForm($form, static::MAIN_ROLES);
    $this->appendRolesToForm($form, array_diff($allRoles, static::IGNORED_ROLES, static::MAIN_ROLES));

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('iucn_who_core.settings')
      ->set('agreement.default.content',$form_state->getValue('user_agreement_content_default'));

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    foreach ($roles as $role) {
      if (in_array($role->id(), static::IGNORED_ROLES)) {
        continue;
      }

      $this->config('iucn_who_core.settings')
        ->set(sprintf('agreement.%s.content', $role->id()), $form_state->getValue('user_agreement_content_' . $role->id()))
        ->set(sprintf('agreement.%s.enabled', $role->id()), $form_state->getValue('user_agreement_enabled_' . $role->id()));
    }

    $this->config('iucn_who_core.settings')->save();
  }

  private function appendRolesToForm(&$form, $roleIds) {
    $config = $this->config('iucn_who_core.settings');
    /** @var \Drupal\user\Entity\Role[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    foreach ($roles as $role) {
      if (!in_array($role->id(), $roleIds)) {
        continue;
      }

      $form['user_agreement'][$role->id()] = [
        '#type' => 'details',
        '#title' => $this->t('Role @role', [
          '@role' => $role->label(),
        ]),
        '#open' => TRUE,
        '#group' => 'user_agreement_tabs',
      ];

      $form['user_agreement'][$role->id()]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('User agreement page content for @role', [
          '@role' => $role->label(),
        ]),
      ];

      $form['user_agreement'][$role->id()]['user_agreement_enabled_' . $role->id()] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => (bool) $config->get(sprintf('agreement.%s.enabled', $role->id())),
        '#id' => sprintf('enable_user_agreement_%s', $role->id()),
      ];

      $form['user_agreement'][$role->id()]['content'] = [
        '#type' => 'container',
        'user_agreement_content_' . $role->id() => [
          '#type' => 'text_format',
          '#title' => $this->t('Content'),
          '#format' => 'html',
          '#default_value' => $config->get(sprintf('agreement.%s.content.value', $role->id())),
          '#description' => $this->t('Leave it blank to display the default user agreement for @role role', [
            '@role' => $role->label(),
          ]),
        ],
        '#states' => [
          'invisible' => [
            'input[name="' . 'user_agreement_enabled_' . $role->id() . '"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }
  }
}
