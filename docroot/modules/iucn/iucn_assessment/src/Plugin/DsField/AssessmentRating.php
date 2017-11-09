<?php

namespace Drupal\iucn_assessment\Plugin\DsField;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ds\Plugin\DsField\DsFieldBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Plugin that renders Rating image.
 *
 * @DsField(
 *   id = "assessment_rating",
 *   title = @Translation("Rating image"),
 *   entity_type = "node",
 *   provider = "node"
 * )
 */
class AssessmentRating extends DsFieldBase {

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

    if (!$node->hasField('field_current_assessment')) {
      return $return;
    }
    if (!$node->field_assessments->count()) {
      return $return;
    }


    // Handle custom revision display.
    $node_revision = _iucn_assessment_display_negociate_assessment_revision($node);

    if (!$node->field_assessments->entity->field_as_global_assessment_level->entity) {
      return $return;
    }

    /* @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $node->field_assessments->entity->field_as_global_assessment_level->entity->get('field_image');
    $years = [$node->field_assessments->entity->field_as_cycle->value];
    $img_value = $node->field_assessments->entity->field_as_global_assessment_level->entity->get('field_image')->getValue()[0];
    /* @var \Drupal\Core\Field\FieldItemListInterface $items */
    foreach ($node->field_assessments as $idx => $assessment) {
      if (!$node->field_assessments[$idx]->entity->access('view')) {
        continue;
      }
      if ($idx == 0) {
        continue;
      }
      $showing_item = $assessment->entity;
      if ($node_revision && $node_revision->id() == $showing_item->id()) {
        $showing_item = $node_revision;
      }

      if ($showing_item->field_as_global_assessment_level->count()) {
        $img_value = $showing_item->field_as_global_assessment_level
          ->entity->get('field_image')->getValue()[0];
      }
      // If the current assessment doesn't have a value, use the first one.
      $items->appendItem($img_value);

      $years[] = $showing_item->field_as_cycle->value;
    }

    /* @var \Drupal\Core\Field\FormatterInterface $formatter */
    $formatter = $this->getFormatter([
      'type' => $this->getFieldConfiguration()['formatter'],
    ]);
    $formatter->prepareView([$items]);
    $view_images = $formatter->viewElements($items, $node->language()->getId());


    $element = [
      '#theme' => 'rating_image_switcher',
      '#images' => $view_images,
      '#years' => $years,
    ];

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    if ($this->bundle() != 'site') {
      return FALSE;
    }
    return parent::isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function formatters() {
    return $this->formatterPluginManager->getOptions('image');
  }

  /**
   * Return the field definition.
   */
  protected function getFieldDefinition() {
    if (!$this->fieldDefinition) {
      $this->fieldDefinition = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', 'assessment_conservation_rating')['field_image'];
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
