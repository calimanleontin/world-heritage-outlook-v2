<?php

namespace Drupal\dropdown_language\Plugin\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;

/**
 * Provides an alternative language switcher block.
 *
 * @Block(
 *   id = "dropdown_language",
 *   admin_label = @Translation("Dropdown Language Selector"),
 *   category = @Translation("Custom Blocks"),
 * )
 */
class DropdownLanguage extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a new DropdownLanguage instance.
   *
   * @param array $block_configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $block_configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($block_configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $block_configuration, $plugin_id, $plugin_definition) {
    return new static(
     $block_configuration,
     $plugin_id,
     $plugin_definition,
     $container->get('language_manager'),
     $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = [];
    $languages = $this->languageManager->getLanguages();
    if (count($languages) > 1) {
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      $links = $this->languageManager->getLanguageSwitchLinks("language_interface", Url::fromRoute('<current>'))->links;

      // Place active language ontop of list.
      if (isset($links[$current_language])) {
        $links = [$current_language => $links[$current_language]] + $links;
        // Set an active class for styling.
        $links[$current_language]['attributes']['class'][] = 'active-language';
      }

      // Get block instance and general settings.
      $block_config = $this->getConfiguration();
      $general_config = $this->configFactory->get('dropdown_language.setting');
      $wrapper_default = $general_config->get('wrapper');
      $display_language_id = $general_config->get('display_language_id');

      // Re-label as per general setting.
      foreach ($links as $lid => $link) {
        switch ($display_language_id) {
          case '0':
            $links[$lid]['title'] = Unicode::strtoupper($lid);
            break;

          case '2':
            $links[$lid]['title'] = isset($block_config['labels'][$lid]) ? $block_config['labels'][$lid] : $link['language']->getName();
            break;
        }
      }

      $dropdown_button = [
        '#type' => 'dropbutton',
        '#subtype' => 'dropdown_language',
        '#links' => $links,
        '#attributes' => [
          'class' => ['dropdown-language-item'],
        ],
        '#attached' => [
          'library' => ['dropdown_language/dropdown-language-selector'],
        ],
      ];
      if ($wrapper_default == 1) {
        $block['switcher'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Switch Language'),
        ];
        $block['switcher']['switch-language'] = $dropdown_button;
      }
      else {
        $block['switch-language'] = $dropdown_button;
      }
    }

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['#attached']['library'][] = 'dropdown_language/dropdown-language-admin';

    $general_config = $this->configFactory->get('dropdown_language.setting');
    $display_language_id = $general_config->get('display_language_id');

    $current_path = Url::fromRoute('<current>')->toString();
    $general_settings = [
      '#type' => 'link',
      '#title' => $this->t('General Settings for all Dropdown Language Blocks'),
      '#url' => Url::fromRoute('dropdown_language.setting', [
          ['destination' => $current_path],
      ]
      ),
      '#attributes' => [
        'class' => ['dropdown-general-settings'],
      ],
    ];

    if ($display_language_id == 2) {
      $block_config = $this->getConfiguration();
      $languages = $this->languageManager->getLanguages();
      $form['labels'] = [
        '#type' => 'details',
        '#title' => $this->t('Custom Labels for Language Names'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      foreach ($languages as $lid => $item) {
        $form['labels'][$lid] = [
          '#type' => 'textfield',
          '#required' => TRUE,
          '#title' => $this->t('Label for <q>@lng</q>', ['@lng' => $item->getName()]),
          '#default_value' => isset($block_config['labels'][$lid]) ? $block_config['labels'][$lid] : $item->getName(),
        ];
      }
      $form['labels']['translation-note'] = [
        '#type' => 'inline_template',
        '#template' => '<dl class="dropdown-language-help"><dt>{{ title }}</dt><dd>{{ text }}</dd></dl>',
        '#context' => [
          'title' => $this->t('How to translate custom labels'),
          'text' => $this->t('Create a unique block instance for each language then assign via Language Visibility per block.  This is due to the general idea that you are now changing labels that were otherwise normally translatable strings by using these Custom Labels.'),
        ],
      ];
      $form['labels']['setting-link'] = $general_settings;
    }
    else {
      $form['setting-link'] = $general_settings;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('labels', $form_state->getValue('labels'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
