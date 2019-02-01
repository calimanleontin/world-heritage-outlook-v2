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
    'administrator',
    'anonymous',
    'authenticated',
    'menu_editor',
    'edit_world_heritage_site_assessments',
    'publish_world_heritage_site_assessments',
    'edit_content_pages',
    'publish_content_pages',
    'edit_world_heritage_site_information',
    'publish_world_heritage_site_information',
    'manage_submissions',
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
    $defaultContent = $config->get('user_agreement_content_default');

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
      '#default_value' => !empty($defaultContent['value']) ? $defaultContent['value'] : '',
    ];

    $allRoles = array_keys($this->entityTypeManager->getStorage('user_role')->loadMultiple());

    $this->appendRolesToForm($form, array_diff($allRoles, static::IGNORED_ROLES));
    $this->appendRolesToForm($form, array_diff(static::IGNORED_ROLES, ['anonymous', 'authenticated']));

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('iucn_who_core.settings')
      ->set('user_agreement_content_default', $form_state->getValue('user_agreement_content_default'));

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    foreach ($roles as $role) {
      if (in_array($role->id(), static::IGNORED_ROLES)) {
        continue;
      }

      $contentKey = 'user_agreement_content_' . $role->id();
      $enabledKey = 'user_agreement_enabled_' . $role->id();

      $this->config('iucn_who_core.settings')
        ->set($contentKey, $form_state->getValue($contentKey))
        ->set($enabledKey, $form_state->getValue($enabledKey));
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

      $tabTitle = $this->t('Role @role', [
        '@role' => $role->label(),
      ]);

      $textTitle = $this->t('User agreement page content for @role', [
        '@role' => $role->label(),
      ]);

      $helpText = $this->t('Leave it blank to display the default user agreement for @role role', [
        '@role' => $role->label()
      ]);

      $form['user_agreement'][$role->id()] = [
        '#type' => 'details',
        '#title' => $tabTitle,
        '#open' => TRUE,
        '#group' => 'user_agreement_tabs',
      ];

      $content = $config->get('user_agreement_content_' . $role->id());
      $enabled = $config->get('user_agreement_enabled_' . $role->id());

      $form['user_agreement'][$role->id()]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $textTitle,
      ];

      $form['user_agreement'][$role->id()]['user_agreement_enabled_' . $role->id()] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => (bool) $enabled,
        '#id' => sprintf('enable_user_agreement_%s', $role->id()),
      ];

      $form['user_agreement'][$role->id()]['content'] = [
        '#type' => 'container',
        'user_agreement_content_' . $role->id() => [
          '#type' => 'text_format',
          '#title' => $this->t('Content'),
          '#format' => 'html',
          '#default_value' => !empty($content['value']) ? $content['value'] : '',
          '#description' => $helpText,
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
