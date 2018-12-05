<?php

namespace Drupal\iucn_assessment\Commands;

use Drupal\iucn_assessment\Plugin\AssessmentCycleCreator;
use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 *
 * In addition to a commandfile like this one, you need a drush.services.yml
 * in root of your module.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 *
 * Add this command to be executed when running database updates.
 * function iucn_assessment_update_8xxx() {
 *   drush_invoke_process('@self','iucn_assessment:delete-revisions');
 * }
 *
 */
class IucnAssessmentCommands extends DrushCommands {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentCycleCreator */
  protected $assessmentCycleCreator;

  /**
   * IucnAssessmentCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AssessmentCycleCreator $assessmentCycleCreator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->assessmentCycleCreator = $assessmentCycleCreator;
  }

  /**
   * Create site assessments for a new cycle by duplicating the ones from an
   * older cycle.
   *
   * @param $cycle
   * @param int $originalCycle
   *
   * @command iucn_assessment:create-assessments
   *
   * @throws \Exception
   */
  public function createAssessments($cycle, $originalCycle = 2017) {
    $this->assessmentCycleCreator->createAssessments($cycle, $originalCycle);
  }

}
