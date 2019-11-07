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
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class AssessmentCycleCreator {

  const CREATED_CYCLES_STATE = 'iucn_assessment.created_cycles';

  const TERM_REPLACEMENTS = [
    2020 => [
      1375 => 1372,
      1385 => 1384,
      1289 => 1294,
      1264 => 1292,
    ],
  ];

  const PROTECTION_PARAGRAPHS_ORDER = [
    2017 => [1330, 1331, 1345, 1332, 1333, 1334, 1335, 1336, 1337, 1338, 1339, 1340, 1341, 1342, 1343],
    2020 => [1333, 1334, 1336, 1332, 1330, 1331, 1345, 1335, 1339, 1337, 1338, 1340, 1341, 1342, 1343],
  ];

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
    $createdCycles = $this->state->get(self::CREATED_CYCLES_STATE, []);
    if (!in_array($originalCycle, $createdCycles)) {
      throw new \Exception('Original cycle assessments are not created.');
    }
    if (in_array($cycle, $createdCycles)) {
      throw new \Exception("$cycle cycle assessments are already created.");
    }
    $createdCycles[] = $cycle;
    $originalAssessmentsIds = $this->nodeStorage->getQuery()
      ->condition('type', 'site_assessment')
      ->condition('field_as_cycle', $originalCycle)
      ->execute();
    foreach ($originalAssessmentsIds as $nid) {
      $originalNode = Node::load($nid);
      $existing = $this->nodeStorage->getQuery()
        ->condition('type', 'site_assessment')
        ->condition('field_as_cycle', $cycle)
        ->condition('field_as_site', $originalNode->get('field_as_site')->target_id)
        ->count()
        ->execute();
      if (!empty($existing)) {
        $this->logger->notice("Assessment \"{$originalNode->getTitle()}\" has already been duplicated for {$cycle} cycle.");
        continue;
      }
      $this->createDuplicateAssessment($originalNode, $cycle, $originalCycle);
    }
    $this->state->set(self::CREATED_CYCLES_STATE, $createdCycles);
  }

  /**
   * Reorder the protection paragraphs based on their order in the $termOrder array.
   *
   * @param \Drupal\node\NodeInterface $assessment
   * @param $termOrder
   */
  public function reorderProtectionParagraphs(NodeInterface $assessment, $termOrder) {
    $termOrder = array_flip($termOrder);

    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $protectionParagraphs = $assessment->field_as_protection->getValue();
    if (empty($protectionParagraphs)) {
      return;
    }

    usort($protectionParagraphs, function ($a, $b) use ($termOrder, $paragraphStorage) {
      $p1 = $paragraphStorage->loadRevision($a['target_revision_id']);
      $p2 = $paragraphStorage->loadRevision($b['target_revision_id']);

      return $termOrder[$p1->field_as_protection_topic->target_id] < $termOrder[$p2->field_as_protection_topic->target_id] ? -1 : 1;
    });

    $assessment->field_as_protection->setValue($protectionParagraphs);
    $assessment->save();
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
    $duplicate->setTitle(str_replace($originalCycle, $cycle, $originalNode->getTitle()));
    $duplicate->setPublished(FALSE);
    $duplicate->setCreatedTime(time());
    $duplicate->setChangedTime(time());
    $duplicate->setRevisionUserId(1);
    $duplicate->set('uid', 1);
    $duplicate->set('field_as_start_date', date(DateTimeItemInterface::DATE_STORAGE_FORMAT, time()));
    $duplicate->set('field_as_end_date', NULL);
    $duplicate->set('field_as_cycle', $cycle);
    $duplicate->set('field_state', AssessmentWorkflow::STATUS_NEW);
    $duplicate->set('field_programmatically_fixed', FALSE);
    $this->createDuplicateReferencedEntities($duplicate, $cycle);
    $this->reorderProtectionParagraphs($duplicate, self::PROTECTION_PARAGRAPHS_ORDER[$cycle]);
    $duplicate->save();
    return $duplicate;
  }

  /**
   * Duplicate all child entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param $cycle
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function createDuplicateReferencedEntities(FieldableEntityInterface $entity, $cycle) {
    $this->logger->info("Creating child duplicates for {$entity->getEntityTypeId()} {$entity->id()}");
    foreach ($entity->getFieldDefinitions() as $fieldName => $fieldSettings) {
      if ($fieldSettings instanceof BaseFieldDefinition || $fieldSettings->getType() != 'entity_reference_revisions') {
        continue;
      }

      for ($i = 0; $i < $entity->get($fieldName)->count(); $i++) {
        $childEntity = $entity->get($fieldName)->get($i)->entity;

        // Remove paragraphs that reference term "Is the protected area valued for its nature conservation values?"
        if ($cycle == 2020 && $childEntity->bundle() == 'as_site_benefit'
          && $childEntity->get('field_as_benefits_category')->target_id == 1263
          && $childEntity->get('field_as_benefits_category')->count() == 1) {
          $entity->get($fieldName)->removeItem($i);
          $i--;
          continue;
        }

        if ($childEntity instanceof FieldableEntityInterface) {
          $this->createDuplicateReferencedEntities($childEntity, $cycle);
        }

        /** @var \Drupal\paragraphs\ParagraphInterface $childEntityClone */
        $childEntityClone = $childEntity->createDuplicate();

        // Replace references to some terms.
        if (in_array($cycle, array_keys(self::TERM_REPLACEMENTS))) {
          if ($childEntityClone->bundle() == 'as_site_threat') {
            $this->replaceTermReferences($childEntityClone, 'field_as_threats_categories', $cycle);
          }
          elseif ($childEntityClone->bundle() == 'as_site_benefit') {
            $this->replaceTermReferences($childEntityClone, 'field_as_benefits_category', $cycle);
          }
        }

        $entity->get($fieldName)->set($i, $childEntityClone);
      }
    }
  }

  /**
   * When creating new cycles, some term references can be migrated. (e.g. “Crop
   * production” => “Crops”.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param $field
   * @param int $cycle
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function replaceTermReferences(FieldableEntityInterface $entity, $field, $cycle) {
    $values = $entity->get($field);
    if ($values->isEmpty()) {
      return;
    }

    for ($i = 0; $i < $values->count(); $i++) {
      $tid = $values->get($i)->target_id;
      if (empty(self::TERM_REPLACEMENTS[$cycle][$tid])) {
        continue;
      }
      $values->get($i)->setValue(self::TERM_REPLACEMENTS[$cycle][$tid]);
    }
  }

}
