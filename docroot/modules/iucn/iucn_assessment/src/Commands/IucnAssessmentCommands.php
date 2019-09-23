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
    $this->logger->critical("Assessments successfully created, please run `drush iucn_assessment:fix-assessments {$cycle}`!!!");
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
      $logger = \Drupal::logger('iucn_assessment');
      /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
      $workflow_service = \Drupal::service('iucn_assessment.workflow');
      $entityTypeManager = \Drupal::entityTypeManager();
      /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
      $nodeStorage = $entityTypeManager->getStorage('node');
      $paragraphStorage = $entityTypeManager->getStorage('paragraph');

      foreach ($nodesIds as $nid) {
        $node = Node::load($nid);
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
