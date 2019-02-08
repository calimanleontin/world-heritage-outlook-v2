<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Class TestSupport populates database with test data for various scenarios.
 */
class TestSupport {

  // Site administrator.
  const ADMINISTRATOR = 'admin@test.ro';

  // IUCN manager.
  const IUCN_MANAGER = 'manager@test.ro';

  // Coordinators.
  const COORDINATOR1 = 'coordinator1@test.ro';
  const COORDINATOR2 = 'coordinator2@test.ro';

  // Assessors.
  const ASSESSOR1 = 'assessor1@test.ro';
  const ASSESSOR2 = 'assessor2@test.ro';

  // Reviewers.
  const REVIEWER1 = 'rev1@test.ro';
  const REVIEWER2 = 'rev2@test.ro';
  const REVIEWER3 = 'rev3@test.ro';

  // Assessments.
  const ASSESSMENT1 = 'assessment1';
  const ASSESSMENT2 = 'assessment2';
  const ASSESSMENT3 = 'assessment3';
  const ASSESSMENT4 = 'assessment4';

  // Hidden fields from the site assessment paragraphs
  const HIDDEN_PARAGRAPH_FIELDS = [
    'field_as_benefits_climate_trend',
    'field_as_benefits_commun_in',
    'field_as_benefits_commun_out',
    'field_as_benefits_commun_wide',
    'field_as_benefits_hab_trend',
    'field_as_benefits_invassp_trend',
    'field_as_benefits_oex_trend',
    'field_as_benefits_pollut_trend',
    'field_as_projects_from',
    'field_as_projects_to'
  ];

  const TABS_WITH_FIELD_AND_PARAGRAPH_TYPES = [
    'threats' => [
      'as_site_threat',
      'field_as_threats_current_text',
      'field_as_threats_current_rating',
      'field_as_threats_potent_text',
      'field_as_threats_potent_rating',
      'field_as_threats_text',
      'field_as_threats_rating'
    ],
    'protection-management' => [
      'as_site_protection',
      'field_as_protection_ov_text',
      'field_as_protection_ov_rating',
      'field_as_protection_ov_out_text',
      'field_as_protection_ov_out_rate',
      'field_as_protection_ov_practices'
    ],
    'assessing-values' => [
      'as_site_value_wh',
      'field_as_vass_wh_text',
      'field_as_vass_wh_state',
      'field_as_vass_wh_trend'
    ],
    'conservation-outlook' => [
      'field_as_global_assessment_text',
      'field_as_global_assessment_level'
    ],
    'benefits' => [
      'as_site_benefit',
      'field_as_benefits_summary'
    ],
    'projects' => [
      'as_site_project'
    ],
    'references' => [
      'as_site_reference',
    ]
  ];

  /**
   * Create all the test data.
   */
  public static function createTestData() {
    // Create test users.
    $users = [
      self::ADMINISTRATOR => ['administrator'],
      self::IUCN_MANAGER => ['iucn_manager'],
      self::COORDINATOR1 => ['coordinator'],
      self::COORDINATOR2 => ['coordinator'],
      self::ASSESSOR1 => ['assessor'],
      self::ASSESSOR2 => ['assessor'],
      self::REVIEWER1 => ['reviewer'],
      self::REVIEWER2 => ['reviewer'],
      self::REVIEWER3 => ['reviewer'],
    ];
    foreach ($users as $user => $roles) {
      self::createUser($user, $roles);
    }

    // Create taxonomy terms in all vocabularies.
    self::createTaxonomyTerms();

    // Create 4 test assessments.
    $assessments = [
      self::ASSESSMENT1,
      self::ASSESSMENT2,
      self::ASSESSMENT3,
      self::ASSESSMENT4,
    ];
    foreach ($assessments as $title) {
      self::createAssessment($title);
    }
  }

  /**
   * Generate 5 terms in each important vocabulary.
   */
  public static function createTaxonomyTerms() {
    /** @var \Drupal\taxonomy\VocabularyInterface[] $vocabularies */
    $vocabularies = Vocabulary::loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      for ($i = 1; $i <= 5; $i++) {
        self::createSampleEntity('taxonomy_term', $vocabulary->id(), [
          'name' => "{$vocabulary->id()} term {$i}",
        ]);
      }
    }
  }

  /**
   * Create an user with certain roles.
   *
   * @param string $mail
   *   The email.
   * @param array $roles
   *   An array of roles as strings.
   *
   * @return int
   *   The user id.
   */
  public static function createUser($mail, $roles = []) {
    $ob = User::create([
      'name' => $mail,
      'mail' => $mail,
    ]);
    $ob->setPassword('password');
    $ob->set('status', 1);
    foreach ($roles as $role) {
      $ob->addRole($role);
    }
    $ob->save();
    return $ob->id();
  }

  /**
   * Create an site_assessment node.
   *
   * @param null $title
   *
   * @return \Drupal\node\NodeInterface
   */
  public static function createAssessment($title = NULL, $fieldsCompleted = []) {
    $node = self::createSampleEntity('node', 'site_assessment', [
      'title' => $title ?: 'Test assessment',
      'created' => time(),
      'uid' => 0,
      'promote' => 0,
      'field_state' => AssessmentWorkflow::STATUS_NEW,
      'status' => 0,
      'field_as_version' => 1,
      'field_as_cycle' => 2020,
    ]);
    foreach ($fieldsCompleted as $fieldName) {

    }
    return $node;
  }

  public static function populateAllFieldsData(FieldableEntityInterface $entity) {
    foreach ($entity->getFieldDefinitions() as $fieldDefinition) {
      $fieldName = $fieldDefinition->getName();
      if (!preg_match('/^field\_/', $fieldName)) {
        continue;
      }
      self::populateFieldData($entity, $fieldDefinition->getName());
    }
  }

  public static function populateFieldData(FieldableEntityInterface $entity, $fieldName) {
    /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
    $fieldDefinition = $entity->get($fieldName)->getFieldDefinition();
    /** @var \Drupal\field\FieldStorageConfigInterface $fieldStorageDefinition */
    $fieldStorageDefinition = $fieldDefinition->getFieldStorageDefinition();
    switch ($fieldDefinition->getType()) {
      case '':
        // Do nothing for these fields.
        break;
      case 'boolean':
        // @todo
        break;

      case 'integer':
        // @todo
        break;

      case 'float':
        // @todo
        break;

      case 'datetime':
        // @todo
        break;

      case 'string':
        // @todo
        break;

      case 'string_long':
        // @todo
        break;

      case 'list_string':
        // @todo
        break;

      case 'entity_reference':
        // @todo
        break;

      case 'entity_reference_revisions':
        $handlerSettings = $fieldDefinition->getSetting('handler_settings');
        $targetType = $fieldDefinition->getSetting('target_type');
        $targetBundle = reset($handlerSettings['target_bundles']);

        $entityTypeManager = \Drupal::entityTypeManager();
        $entityType = $entityTypeManager->getDefinition($targetType);
        $entityStorage = $entityTypeManager->getStorage($targetType);

        $cardinality = $fieldStorageDefinition->getCardinality();
        if ($cardinality == -1) {
          $cardinality = 5;
        }

        for ($i = 0; $i < $cardinality; $i++) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
          $childEntity = $entityStorage->create([
            $entityType->getKey('bundle') => $targetBundle,
          ]);
          self::populateAllFieldsData($childEntity);
          $childEntity->save();
          $entity->get($fieldName)->appendItem($childEntity);
        }
        break;

      default:
        // @todo remove this
        var_dump($fieldDefinition->getType());
    }
  }

  /**
   * Retrieve a taxonomy term from a specified vocabulary.
   *
   * @param $vid
   *  Vocabulary id.
   * @param int|null $termIndex
   *  If provided, the term with name "$vid term $termIndex" will be loaded.
   *  See TestSupport::createTaxonomyTerms.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   */
  public static function getTaxonomyTerm($vid, $termIndex = NULL) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vid);
    if (!empty($termIndex)) {
      $query->condition('name', "{$vid} term {$termIndex}");
    }
    $ids = $query->execute();
    return !empty($ids)
      ? Term::load(current($ids))
      : NULL;
  }

  public static function createSampleEntity($type, $bundle, $fields = []) {
    switch ($type) {
      case 'node':
        $entity = Node::create([
          'type' => $bundle,
        ]);
        break;

      case 'taxonomy_term':
        $entity = Term::create([
          'vid' => $bundle,
        ]);
        break;

      case 'paragraph':
        $entity = Paragraph::create([
          'type' => $bundle,
        ]);
        break;

      default:
        throw new \InvalidArgumentException('Invalid entity type provided. Accepted types are: node, taxonomy_term, paragraph.');
    }
    foreach ($fields as $field => $value) {
      $entity->set($field, $value);
    }
    $entity->save();
    return $entity;
  }

}
