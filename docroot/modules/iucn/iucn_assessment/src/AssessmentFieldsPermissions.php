<?php

namespace Drupal\iucn_assessment;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\DependencyInjection\ContainerInterface;


class AssessmentFieldsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns an array of assessment node fields permissions.
   *
   * @return array
   *   The assessment node fields permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getPermissions() {
    $permissions = [];

    /** @var \Drupal\field\Entity\FieldConfig[] $fields */
    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'site_assessment');
    $fields = array_filter($fields, function ($field) {
      // We want permissions only for custom fields.
      return preg_match('/^field\_.+/', $field);
    }, ARRAY_FILTER_USE_KEY);

    /** @var \Drupal\workflow\Entity\WorkflowState[] $states */
    $states = $this->entityTypeManager->getStorage('workflow_state')->loadByProperties(['wid' => 'assessment']);
    uasort($states, function (WorkflowState $a, WorkflowState $b){
      return ($a->getWeight() < $b->getWeight()) ? -1 : 1;
    });

    // $i is used to display the permissions grouped by field.
    $i = 1000;
    foreach ($fields as $fieldId => $field) {
      $key = '<span style="display: none;">' . $i++ . '</span>';
      $translationParameters = [
        '%field' => $field->label(),
        '%fieldId' => $fieldId,
      ];

      $permissions["edit field {$fieldId}"] = [
        'title' => $this->t("{$key} %fieldId - edit", $translationParameters),
        'description' => $this->t("Edit value(s) for %field", $translationParameters),
      ];

      if ($field->getFieldStorageDefinition()->getCardinality() === 1) {
        continue;
      }
      $permissions["add more field {$fieldId}"] = [
        'title' => $this->t("{$key} %fieldId - add more", $translationParameters),
        'description' => $this->t("Add more values to %field", $translationParameters),
      ];
      $permissions["delete field {$fieldId}"] = [
        'title' => $this->t("{$key} %fieldId - delete", $translationParameters),
        'description' => $this->t("Delete values from %field", $translationParameters),
      ];
      $permissions["reorder field {$fieldId}"] = [
        'title' => $this->t("{$key} %fieldId - reorder", $translationParameters),
        'description' => $this->t("Reorder values in %field", $translationParameters),
      ];
    }

    return $permissions;
  }
}
