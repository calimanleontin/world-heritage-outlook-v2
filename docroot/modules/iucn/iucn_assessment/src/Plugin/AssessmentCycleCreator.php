<?php

namespace Drupal\iucn_assessment\Plugin;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

class AssessmentCycleCreator {

  const CREATED_CYCLES_STATE = 'iucn_assessment.created_cycles';

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface */
  protected $entityFieldManager;

  /** @var \Drupal\Core\State\StateInterface */
  protected $state;

  /** @var array */
  protected $availableCycles = [];

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, StateInterface $state) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->state = $state;
    /** @var \Drupal\field\FieldConfigInterface $cycleFieldDefinition */
    $cycleFieldDefinition = $this->entityFieldManager->getFieldDefinitions('node', 'site_assessment')['field_as_cycle'];
    $this->availableCycles = $cycleFieldDefinition->getSetting('allowed_values');
  }

  /**
   * @param $cycle
   * @param string $originalCycle
   *
   * @throws \Exception
   */
  public function createAssessments($cycle, $originalCycle = '2017') {
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
  }
}