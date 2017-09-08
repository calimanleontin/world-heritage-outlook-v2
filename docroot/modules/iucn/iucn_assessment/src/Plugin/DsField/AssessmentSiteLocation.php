<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ds\Plugin\DsField\DsFieldBase;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Plugin that renders Rating image.
 *
 * @DsField(
 *   id = "assessment_site_location",
 *   title = @Translation("Site location"),
 *   entity_type = "node",
 *   provider = "node"
 * )
 */
class AssessmentSiteLocation extends DsFieldBase {

  /**
   * Field Definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * Formatter.
   *
   * @var \Drupal\Core\Field\FormatterInterface.
   */
  protected $formatter;

  /**
   * Formatter Plugin Manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $formatterPluginManager;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a Display Suite field plugin.
   */
  public function __construct($configuration,
                              $plugin_id,
                              $plugin_definition,
                              PluginManagerInterface $formatter_plugin_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              EntityFieldManagerInterface $entity_field_manager) {
    $this->formatterPluginManager = $formatter_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.formatter'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    /* @var $node NodeInterface */
    $node = $this->entity();

    $return = [];

    if (!$node->field_as_site->count()) {
      return $return;
    }
    if (empty($node->field_as_site->entity->field_geolocation->getValue())) {
      return $return;
    }

    /* @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $node->field_as_site->entity->get('field_geolocation');

    /* @var \Drupal\Core\Field\FormatterInterface $formatter */
    $formatter = $this->getFormatter([
      'type' => $this->getFieldConfiguration()['formatter'],
    ]);


    $array = $items->getIterator()->getArrayCopy();
    $formatter->prepareView([$array]);

    /* @var \Drupal\Core\Field\FormatterInterface $formatter */
    $formatter = $this->getFormatter([
      'type' => $this->getFieldConfiguration()['formatter'],
    ]);


    $formatter->prepareView([$items]);

    return $formatter->viewElements($items, $node->field_as_site->entity->language()->getId());
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    if ($this->bundle() != 'site_assessment') {
      return FALSE;
    }
    return parent::isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function formatters() {
    return $this->formatterPluginManager->getOptions('geofield');
  }

  /**
   * Return the field definition.
   */
  protected function getFieldDefinition() {
    if (!$this->fieldDefinition) {
      $this->fieldDefinition = $this->entityFieldManager->getFieldDefinitions('node', 'site')['field_geolocation'];
    }

    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $formatter_id = $form_state->getUserInput()['fields'][$this->getName()]['plugin']['type'];

    $formatter = $this->getFormatter([
      'type' => $formatter_id,
    ]);

    return [
      'formatter' => $formatter->settingsForm($form, $form_state),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary($settings) {
    /* @var \Drupal\Core\Field\FormatterInterface $formatter */
    $formatter = $this->getFormatter([
      'type' => $this->getFieldConfiguration()['formatter'],
    ]);

    if ($formatter) {
      return $formatter->settingsSummary();
    }
    else {
      return [];
    }
  }

  /**
   * Get the formatter configuration.
   */
  protected function getFormatterConfiguration() {
    $config = $this->getConfiguration();

    return isset($config['formatter']) ? $config['formatter'] : [];
  }

  /**
   * Return the field formatter.
   */
  protected function getFormatter(array $configuration = []) {
    if (!isset($configuration['settings'])) {
      $configuration['settings'] = $this->getFormatterConfiguration();
    }

    return $this->formatterPluginManager->getInstance([
      'field_definition' => $this->getFieldDefinition(),
      'view_mode' => $this->viewMode(),
      'prepare' => TRUE,
      'configuration' => $configuration,
    ]);
  }

}
