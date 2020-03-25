<?php

namespace Drupal\iucn_assessment\Commands;

use Drupal\Core\Url;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\iucn_assessment\Plugin\AssessmentCycleCreator;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\iucn_fields\Plugin\TermAlterService;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

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

  /** @var TermAlterService */
  protected $termAlterService;

  /**
   * IucnAssessmentCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AssessmentCycleCreator $assessmentCycleCreator, TermAlterService $termAlterService) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->assessmentCycleCreator = $assessmentCycleCreator;
    $this->termAlterService = $termAlterService;
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
    $this->logger->info("Assessments successfully created, now please run: drush --uri=https://www.worldheritageoutlook.iucn.org iucn_assessment:fix-assessments {$cycle}");
  }

  /**
   *  Delete all revisions for old assessments.
   *  Set "Published" status to all existing assessments.
   *  Force workflow status for old assessments.
   *  Fix broken values references.
   *  Delete all protection management paragraphs with no values.
   *
   * @param null $cycle
   *
   * @command iucn_assessment:fix-assessments
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function fixAssessments($cycle = NULL) {
    $nodesIds = $this->getAssessmentsInRange($cycle);
    while (!empty($nodesIds)) {
      foreach ($nodesIds as $nid) {
        $node = Node::load($nid);
        $this->assessmentCycleCreator->fixAssessmentsFields($node);
      }
      $nodesIds = $this->getAssessmentsInRange($cycle);
    }
  }

  protected function getAssessmentsInRange($cycle = NULL, $start = NULL, $length = 50) {
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'site_assessment');
    $or = $query->orConditionGroup()
      ->notExists('field_programmatically_fixed')
      ->condition('field_programmatically_fixed', 0);
    $query->condition($or);
    if (!empty($cycle)) {
      $query->condition('field_as_cycle', $cycle);
    }
    return $query->sort('nid')
      ->range($start, $length)
      ->execute();
  }

  /**
   * Remove paragraphs from assessments that references a hidden term for a
   * cycle
   *
   * @param $cycle
   *
   * @options dry-run Option to make command run without doing any changes
   *
   * @command iucn_assessment:remove-hidden-terms-references
   *
   * @throws \Exception
   */
  public function removeHiddenTermsReferences($cycle, $options = ['dry-run' => FALSE]) {
    $dryRun = $options['dry-run'] !== FALSE;

    $termIds = $this->termAlterService->getHiddenTermsForCycle($cycle);

    /** @var Term[] $terms */
    $terms = Term::loadMultiple($termIds);

    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    $fieldStorageConfigs = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties(
        [
          'entity_type' => 'paragraph',
          'field_type' => 'entity_reference',
          'status' => TRUE,
        ]
      );

    //Condition to exclude assessment that have been excluded by other paragraphs
    $verifiedAssessments = $deletedEntities = [];
    $paragraphCount = 0;

    $brokenTerms = [];
    foreach ($terms as $term) {
      foreach ($fieldStorageConfigs as $fieldStorageConfig) {
        if (empty($fieldStorageConfig->getSetting('handler_settings'))) {
          continue;
        }

        if (!array_key_exists($term->bundle(), $fieldStorageConfig->getSetting('handler_settings')['target_bundles'])) {
          continue;
        }

        $query = $paragraphStorage->getQuery()
          ->condition($fieldStorageConfig->get('field_name'), $term->id());
        if ($verifiedAssessments) {
          $query->condition('parent_id', $verifiedAssessments, 'NOT IN');
        }

        $paragraphIds = $query->execute();
        if (empty($paragraphIds)) {
          continue;
        }

        foreach ($paragraphIds as $paragraphId) {
          $paragraph = Paragraph::load($paragraphId);
          $node = $paragraph->getParentEntity();
          if (!$node instanceof NodeInterface) {
            continue;
          }

          if ($node->bundle() != 'site_assessment' || $node->get('field_as_cycle')->value != $cycle) {
            $verifiedAssessments[] = $node->id();
            continue;
          }

          foreach ($node->getFields() as $field) {
            if (!$field instanceof EntityReferenceRevisionsFieldItemList) {
              continue;
            }

            $fieldValues = array_column($field->getValue(), 'target_id');
            $index = array_search($paragraph->id(), $fieldValues);
            if ($index !== FALSE) {
              $brokenTerms[$term->id()] = $term->label();
              $paragraphCount++;
              if (!$dryRun) {
                $node->get($field->getName())->removeItem($index);
                $node->save();
                $this->logger->warning("Deleted paragraph \"{$paragraph->id()}\" from node \"{$node->id()}\" and field \"{$field->getName()}\" at position {$index} !");
              }
              else {
                if (empty($deletedEntities[$node->id()])) {
                  $deletedEntities[$node->id()] = [
                    'name' => $node->label(),
                    'url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()], ['absolute' => TRUE])
                      ->toString(),
                    'paragraphs' => [],
                    'terms' => [],
                    'termIds' => [],
                  ];
                }
                if (empty($deletedEntities[$node->id()]['paragraphs'][$field->getName()])) {
                  $deletedEntities[$node->id()]['paragraphs'][$field->getName()] = [];
                }

                $deletedEntities[$node->id()]['paragraphs'][$field->getName()][] = $paragraphId;
                $deletedEntities[$node->id()]['terms'][] = $term->label();
                $deletedEntities[$node->id()]['termIds'][] = $term->id();
              }
            }
          }
        }
      }
    }

    if (!empty($deletedEntities)) {
      $this->logger->warning("Name;Edit URL;Summary;Terms;Term ids");
      foreach ($deletedEntities as $nodeId => $nodeData) {
        $brokenParagraphSummary = $brokenParagraphs = [];
        foreach ($nodeData['paragraphs'] as $field => $paragraphIds) {
          $paragraphIds = array_unique($paragraphIds);
          $brokenIds = implode(', ', $paragraphIds);
          $brokenParagraphSummary[] = count($paragraphIds) . " broken row(s) of type $field: ({$brokenIds})";
        }

        $brokenParagraphsValue = implode(", ", $brokenParagraphSummary);
        sort($nodeData['termIds']);
        $row = sprintf("%s; %s; %s; %s; %s",
          $nodeData['name'],
          $nodeData['url'],
          $brokenParagraphsValue,
          implode(', ', array_unique($nodeData['terms'])),
          implode(', ', array_unique($nodeData['termIds']))
        );
        $this->logger->warning($row);
      }
      $this->logger->warning("\n");
      $this->logger->warning(sprintf("Found %s broken terms: %s", count($brokenTerms), implode(',', $brokenTerms)));
    }

    $this->logger->warning("Found $paragraphCount broken paragraphs!");
  }
}
