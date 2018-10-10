<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class AssessmentCycleCreator {

  const CREATED_CYCLES_STATE = 'iucn_assessment.created_cycles';

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface */
  protected $entityFieldManager;

  /** @var \Drupal\Core\State\StateInterface */
  protected $state;

  /** @var \Drupal\Core\Logger\LoggerChannelInterface */
  protected $logger;

  /** @var int[] */
  protected $availableCycles = [];

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, StateInterface $state, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->entityFieldManager = $entityFieldManager;
    $this->state = $state;
    $this->logger = $loggerChannelFactory->get('iucn_assessment.cycle_creator');

    $siteAssessmentFields = $this->entityFieldManager->getFieldDefinitions('node', 'site_assessment');
    $fieldAsCycleConfig = $siteAssessmentFields['field_as_cycle'];
    $this->availableCycles = $fieldAsCycleConfig->getSetting('allowed_values');
  }

  /**
   * Create site assessments for a new cycle by duplicating the ones from an
   * older cycle.
   *
   * @param int $cycle
   * @param int $originalCycle
   *
   * @throws \Exception
   */
  public function createAssessments($cycle, $originalCycle = 2017) {
    if (!array_key_exists($cycle, $this->availableCycles) || !array_key_exists($originalCycle, $this->availableCycles)) {
      throw new \InvalidArgumentException('Invalid cycle parameter. Available cycles: ' . implode(', ', array_keys($this->availableCycles)));
    }
    $createdCycles = $this->state->get(self::CREATED_CYCLES_STATE);
    if (!in_array($originalCycle, $createdCycles)) {
      throw new \Exception('Original cycle assessments are not created.');
    }
    if (in_array($cycle, $createdCycles)) {
      throw new \Exception("$cycle cycle assessments are already created.");
    }
    $createdCycles[] = $cycle;
    $this->state->set(self::CREATED_CYCLES_STATE, $createdCycles);

    $originalAssessmentsIds = $this->nodeStorage->getQuery()
      ->condition('type', 'site_assessment')
      ->condition('field_as_cycle', $originalCycle)
      ->execute();
    foreach ($originalAssessmentsIds as $nid) {
      $originalNode = Node::load($nid);
      $this->createDuplicateAssessment($originalNode, $cycle, $originalCycle);
    }
  }

  /**
   * Duplicate the original assessment node and all its child-entities. A node
   * with a new id is returned.
   *
   * @param \Drupal\node\NodeInterface $originalNode
   * @param int $cycle
   * @param int $originalCycle
   *
   * @return \Drupal\node\NodeInterface
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createDuplicateAssessment(NodeInterface $originalNode, $cycle, $originalCycle) {
    $this->logger->notice("Duplicating \"{$originalNode->getTitle()}\" assessment for {$cycle} cycle.");
    $duplicate = $originalNode->createDuplicate();
    $duplicate->set('field_as_cycle', $cycle);
    $duplicate->setTitle(str_replace($originalCycle, $cycle, $originalNode->getTitle()));
    $this->createDuplicateReferencedEntities($duplicate);
    $duplicate->save();
    return $duplicate;
  }

  /**
   * Duplicate all child entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   */
  protected function createDuplicateReferencedEntities(FieldableEntityInterface $entity) {
    foreach ($entity->getFieldDefinitions() as $fieldName => $fieldSettings) {
      if (!$fieldSettings instanceof BaseFieldDefinition && in_array($fieldSettings->getType(), [
          'entity_reference',
          'entity_reference_revisions',
        ])) {
        foreach ($entity->{$fieldName} as &$value) {
          $childEntity = $value->entity;
          if ($childEntity instanceof FieldableEntityInterface) {
            $this->createDuplicateReferencedEntities($childEntity);
          }
          $value->entity = $childEntity->createDuplicate();
        }
      }
    }
  }
}