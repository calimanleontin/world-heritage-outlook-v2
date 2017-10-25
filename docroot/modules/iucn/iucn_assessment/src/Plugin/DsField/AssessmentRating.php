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

    $years = [];
    $image_containers = [];

    // Handle custom revision display.
    $node_revision = _iucn_assessment_display_negociate_assessment_revision($node);

    /* @var \Drupal\Core\Field\FieldItemListInterface $items */
    foreach ($node->field_assessments as $idx => $assessment) {
      if (!$node->field_assessments[$idx]->entity->access('view')) {
        continue;
      }
      $showing_item = $assessment->entity;
      $class = 'coming-soon';
      $title = t('Coming soon');
      if ($node_revision && $node_revision->id() == $showing_item->id()) {
        $showing_item = $node_revision;
      }
      if (!empty($showing_item->field_as_global_assessment_level->entity)) {
        /* @var \Drupal\taxonomy\Entity\Term $term */
        $term = $showing_item->field_as_global_assessment_level->entity;
        $title = $term->getName();
        if (!empty($term->field_css_identifier->value)) {
          $class = $term->field_css_identifier->value;
        }
      }

      $image_containers[] = [
        '#markup' => '<div class="image-rating-container ' . $class . '" title="' . $title . '"></div>'
      ];

      $years[] = $showing_item->field_as_cycle->value;
    }

    $element = [
      '#theme' => 'rating_image_switcher',
      '#images' => $image_containers,
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

}
