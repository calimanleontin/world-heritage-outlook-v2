<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;

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

  /** @var \Drupal\Core\Field\FieldDefinitionInterface[] */
  protected $siteAssessmentFields = [];

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, StateInterface $state, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->entityFieldManager = $entityFieldManager;
    $this->state = $state;
    $this->logger = $loggerChannelFactory->get('iucn_assessment.cycle_creator');

    $this->siteAssessmentFields = $this->entityFieldManager->getFieldDefinitions('node', 'site_assessment');
    $this->availableCycles = $this->siteAssessmentFields['field_as_cycle']->getSetting('allowed_values');
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
      $this->logger->notice("Duplicating \"{$originalNode->getTitle()}\" assessment for {$cycle} cycle.");
      $duplicate = $originalNode->createDuplicate();
      $duplicate->set('field_as_cycle', $cycle);
      $duplicate->setTitle(str_replace($originalCycle, $cycle, $originalNode->getTitle()));
      foreach ($this->siteAssessmentFields as $fieldName => $fieldSettings) {
        if (!$fieldSettings instanceof BaseFieldDefinition) { //  && in_array($fieldSettings->getType(), ['entity_reference', 'entity_reference_revisions'])
          switch ($fieldSettings->getType()) {
            case 'entity_reference':
            case 'entity_reference_revisions':
              foreach ($duplicate->{$fieldName} as &$value) {
                $value->entity = $value->entity->createDuplicate();
              }
              break;
          }
        }
      }
      $duplicate->save();
    }
  }
}