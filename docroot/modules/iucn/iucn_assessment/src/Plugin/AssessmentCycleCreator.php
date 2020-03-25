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
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

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
    $idx = 1;
    foreach ($originalAssessmentsIds as $nid) {
      $originalNode = Node::load($nid);
      $this->logger->notice(t("[@idx/@total] Duplicating \"@title\" assessment for @cycle cycle."), [
        '@title' => $originalNode->getTitle(),
        '@cycle' => $cycle,
        '@idx' => sprintf("%1$03d", $idx++),
        '@total' => count($originalAssessmentsIds),
      ]);
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
      $p1 = !empty($a['entity']) && $a['entity'] instanceof ParagraphInterface
        ? $a['entity']
        : $paragraphStorage->loadRevision($a['target_revision_id']);
      $p2 = !empty($b['entity']) && $b['entity'] instanceof ParagraphInterface
        ? $b['entity']
        : $paragraphStorage->loadRevision($b['target_revision_id']);

      return $termOrder[$p1->field_as_protection_topic->target_id] < $termOrder[$p2->field_as_protection_topic->target_id] ? -1 : 1;
    });

    $assessment->field_as_protection->setValue($protectionParagraphs);
  }

  /**
   * Duplicate the original assessment node and all its child-entities. A node
   * with a new id is returned.
   *
   * @param \Drupal\node\NodeInterface $originalNode
   * @param $cycle
   * @param $originalCycle
   *
   * @return \Drupal\node\NodeInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function createDuplicateAssessment(NodeInterface $originalNode, $cycle, $originalCycle) {
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
    $this->fixAssessmentsFields($duplicate);
    return $duplicate;
  }

  public function fixAssessmentsFields(NodeInterface $node) {
    $logger = \Drupal::logger('iucn_assessment');
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = $entityTypeManager->getStorage('node');
    $paragraphStorage = $entityTypeManager->getStorage('paragraph');

    $cycle = (int) $node->field_as_cycle->value;
    if ($cycle < 2020) {
      // Delete all revisions for old assessments.
      $defaultVid = $node->getRevisionId();
      $vids = $nodeStorage->revisionIds($node);
      foreach ($vids as $vid) {
        if ($vid != $defaultVid) {
          $logger->info("Deleting revision vid={$vid} for assessment {$node->getTitle()} ({$node->id()})");
          $nodeStorage->deleteRevision($vid);
        }
      }

      // Force workflow status for old assessments.
      $new_state = $node->isPublished()
        ? AssessmentWorkflow::STATUS_PUBLISHED
        : AssessmentWorkflow::STATUS_DRAFT;
      $workflow_service->forceAssessmentState($node, $new_state, FALSE);
      $logger->info("Force workflow status \"{$new_state}\" for assessment \"{$node->getTitle()} ({$node->id()})\"");
    }
    else {
      // Delete projects ending before assessment cycle.
      $projects = $node->get('field_as_projects')->getValue();
      $validProjects = [];
      foreach ($projects as $project) {
        $paragraph = Paragraph::load($project['target_id']);
        if (empty($paragraph->field_as_projects_to->value)
          || $paragraph->field_as_projects_to->value >= "{$cycle}-01-01"
          || $paragraph->field_as_projects_to->value == '0001-01-01') {
          $validProjects[] = $project;
        }
      }
      $noProjectsDeleted = count($projects) - count($validProjects);
      if ($noProjectsDeleted > 0) {
        $logger->info("Removed {$noProjectsDeleted} projects paragraphs for assessment {$node->id()}");
        $node->set('field_as_projects', $validProjects);
      }
    }

    // Fix broken values references.
    $wh_values = $node->get('field_as_values_wh')->getValue();
    $other_values = $node->get('field_as_values_bio')->getValue();

    $threats = array_merge($node->get('field_as_threats_current')
      ->getValue(), $node->get('field_as_threats_potential')->getValue());

    foreach ($threats as $threat) {
      /** @var \Drupal\paragraphs\ParagraphInterface $threat_paragraph */
      $threat_paragraph = $paragraphStorage->loadRevision($threat['target_revision_id']);
      $this->assessmentFixReferences($threat_paragraph, 'field_as_threats_values_wh', $wh_values, $node->id(), $logger);
      $this->assessmentFixReferences($threat_paragraph, 'field_as_threats_values_bio', $other_values, $node->id(), $logger);
    }

    // Delete all protection and management paragraphs with no values.
    $protection_paragraphs = $node->get('field_as_protection')->getValue();
    $update_protection = FALSE;
    foreach ($protection_paragraphs as $idx => &$protection_paragraph) {
      $paragraph = $paragraphStorage->loadRevision($protection_paragraph['target_revision_id']);
      $delete = TRUE;
      if (!empty($paragraph)) {
        $fields = [
          'field_as_description',
          'field_as_protection_rating',
          'field_as_protection_topic',
        ];
        foreach ($fields as $field) {
          if (!empty($paragraph->get($field)->getValue())) {
            $delete = FALSE;
            break;
          }
        }
      }
      if ($delete) {
        unset($protection_paragraphs[$idx]);
        $update_protection = TRUE;
      }
    }
    if ($update_protection) {
      $node->get('field_as_protection')->setValue($protection_paragraphs);
      $logger->info("Removed empty protection paragraph for assessment {$node->id()}");
    }

    // Migrate references from text field to paragraph.
    $this->assessmentMigrateReferences($node, $logger);
    $node->set('field_programmatically_fixed', TRUE);
    $node->save();
  }

  protected function assessmentMigrateReferences(NodeInterface $node, $logger) {
    $default_language = \Drupal::languageManager()
      ->getDefaultLanguage()
      ->getId();
    $languages = \Drupal::languageManager()->getLanguages();

    $references = $node->get('field_as_references')->getValue();
    if (empty($references)) {
      return;
    }

    $alreadyMigratedReferences = [];
    foreach ($node->get('field_as_references_p')->getValue() as $value) {
      $paragraph = Paragraph::load($value['target_id']);
      $alreadyMigratedReferences[] = $paragraph->field_reference->value;
    }

    foreach ($references as $idx => $reference) {
      $reference = $reference['value'];
      if (empty($reference) || in_array($reference, $alreadyMigratedReferences)) {
        continue;
      }
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      $paragraph = Paragraph::create([
        'type' => 'as_site_reference',
        'field_reference' => $reference,
      ]);

      // Add translation.
      foreach ($languages as $language) {
        $lang_id = $language->getId();
        if ($lang_id == $default_language) {
          continue;
        }
        if ($node->hasTranslation($lang_id)) {
          $translated_reference = $node->getTranslation($lang_id)->field_as_references->getValue()[$idx]['value'];
          $paragraph->addTranslation($lang_id, [
            'field_reference' => $translated_reference,
          ]);
        }
      }

      $paragraph->save();
      $node->get('field_as_references_p')->appendItem([
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ]);
    }
    $logger->info("Migrated references for assessment: {$node->getTitle()}");
    $logger->info("Memory usage: " . round(memory_get_usage() / 1048576, 2) . "MB");
  }

  protected function assessmentFixReferences(ParagraphInterface $threat, $field, array $values, $assessment_id, LoggerInterface $logger) {
    $assessmentNode = Node::load($assessment_id);
    $paragraphStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $referencedValues = $threat->get($field)->getValue();
    if (!empty($referencedValues)) {
      $update = FALSE;

      foreach ($referencedValues as $key => $referencedValue) {
        if (in_array($referencedValue['target_revision_id'], array_column($values, 'target_revision_id'))) {
          continue;
        }

        $valueIsFixed = FALSE;
        $brokenValues = [];
        /** @var ParagraphInterface $referencedParagraph */
        $referencedParagraph = $paragraphStorage->load($referencedValue['target_id']);
        $searchById = in_array($referencedValue['target_id'], array_column($values, 'target_id'));
        foreach ($values as $value) {
          if ($searchById === TRUE) {
            if ($value['target_id'] == $referencedValue['target_id']) {
              $valueIsFixed = $update = TRUE;
              $referencedValues[$key] = $value;
              break;
            }
            continue;
          }

          $valueParagraph = $paragraphStorage->loadRevision($value['target_revision_id']);
          if ($referencedParagraph->field_as_values_value->value == $valueParagraph->field_as_values_value->value) {
            $valueIsFixed = $update = TRUE;
            $referencedValues[$key] = $value;
            break;
          }
        }

        if ($valueIsFixed === FALSE) {
          $brokenValues[] = $referencedParagraph->field_as_values_value->value;
        }
      }

      if (!empty($brokenValues)) {
        $numberOfBrokenValues = count($brokenValues);
        $brokenValuesText = implode('; ', $brokenValues);
        $logger->warning("[{$assessmentNode->getTitle()}] Threat {$threat->field_as_threats_threat->value}' has {$numberOfBrokenValues} broken value(s): {$brokenValuesText}");
      }

      if ($update === TRUE) {
        $logger->info("[{$assessmentNode->getTitle()}] Fixed threat '{$threat->field_as_threats_threat->value}'");
        $threat->get($field)->setValue($referencedValues);
        $threat->save();
      }
    }
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
