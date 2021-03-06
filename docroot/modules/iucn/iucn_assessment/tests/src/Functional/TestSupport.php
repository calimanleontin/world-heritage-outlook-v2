<?php

namespace Drupal\Tests\iucn_assessment\Functional;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Class TestSupport populates database with test data for various scenarios.
 */
class TestSupport {

  // Site administrator.
  const ADMINISTRATOR = 'administrator@example.com';

  // IUCN manager.
  const IUCN_MANAGER = 'iucn_manager@example.com';

  // Coordinators.
  const COORDINATOR1 = 'coordinator1@example.com';
  const COORDINATOR2 = 'coordinator2@example.com';

  // Assessors.
  const ASSESSOR1 = 'assessor1@example.com';
  const ASSESSOR2 = 'assessor2@example.com';

  // Reviewers.
  const REVIEWER1 = 'reviewer1@example.com';
  const REVIEWER2 = 'reviewer2@example.com';
  const REVIEWER3 = 'reviewer3@example.com';

  // References reviews.
  const REFERENCES_REVIEWER1 = 'referencesreviewer1@example.com';
  const REFERENCES_REVIEWER2 = 'referencesreviewer2@example.com';
  const REFERENCES_REVIEWER3 = 'referencesreviewer3@example.com';

  // Assessments.
  const ASSESSMENT1 = 'assessment1';
  const ASSESSMENT2 = 'assessment2';
  const ASSESSMENT3 = 'assessment3';
  const ASSESSMENT4 = 'assessment4';

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
      self::REFERENCES_REVIEWER1 => ['references_reviewer'],
      self::REFERENCES_REVIEWER2 => ['references_reviewer'],
      self::REFERENCES_REVIEWER3 => ['references_reviewer'],
    ];
    foreach ($users as $user => $roles) {
      self::createUser($user, $roles);
    }

    // Create taxonomy terms in all vocabularies.
    self::createTaxonomyTerms();
    // Create nodes for all content types.
    self::createNodes();

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
   * Generate 5 nodes in each important content type.
   */
  public static function createNodes() {
    // We don't want to create site_assessment nodes because there are specific
    // tests for this type of nodes.
    $nodeTypes = NodeType::loadMultiple([
      'benefit',
      'decision',
      'faq',
      'news',
      'page',
      'publication',
      'site',
    ]);
    foreach ($nodeTypes as $nodeType) {
      for ($i = 1; $i <= 5; $i++) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = static::createSampleEntity('node', $nodeType->id(), [
          'title' => "{$nodeType->id()} node {$i}",
        ]);
        static::populateAllFieldsData($node);
        $node->save();
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
  public static function createUser($mail, $roles = [], $acceptedAgreement = TRUE) {
    $ob = User::create([
      'name' => $mail,
      'mail' => $mail,
      'field_accepted_agreement' => $acceptedAgreement,
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
    /** @var \Drupal\node\NodeInterface $node */
    $node = self::createSampleEntity('node', 'site_assessment', [
      'title' => $title ?: 'Test assessment',
      'created' => time(),
      'uid' => 0,
      'promote' => 0,
      'status' => 0,
      'field_as_version' => 1,
      'field_as_cycle' => 2020,
    ]);
    return $node;
  }

  public static function populateAllFieldsData(FieldableEntityInterface $entity, $maxChildParagraphs = 3) {
    $excludedFields = [
      'field_assessments',
      'field_current_assessment',
      'field_as_protection',
      'field_coordinator',
      'field_assessor',
      'field_reviewers',
      'field_references_reviewer',
    ];
    $fieldDefinitions = $entity->getFieldDefinitions();

    foreach ($fieldDefinitions as $fieldDefinition) {
      $fieldName = $fieldDefinition->getName();
      if ((!preg_match('/^field\_/', $fieldName) && !in_array($fieldName, ['title', 'name']))
        || in_array($fieldName, $excludedFields)) {
        continue;
      }
      self::updateFieldData($entity, $fieldDefinition->getName(), $maxChildParagraphs);
    }

    if (array_key_exists('field_as_protection', $fieldDefinitions)) {
      foreach ($entity->field_as_protection as &$value) {
        /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
        $paragraph = $value->entity;
        static::populateAllFieldsData($paragraph, $maxChildParagraphs);
        $paragraph->save();
      }
    }
  }

  public static function updateFieldData(FieldableEntityInterface &$entity, $fieldName, $maxChildParagraphs = 3) {
    $fieldItemList = $entity->get($fieldName);
    /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
    $fieldDefinition = $fieldItemList->getFieldDefinition();
    /** @var \Drupal\field\FieldStorageConfigInterface $fieldStorageDefinition */
    $fieldStorageDefinition = $fieldDefinition->getFieldStorageDefinition();
    // If we already generated a value for this field, we will generate a new
    // one, different from the first one.
    $hasValue = !empty($fieldItemList->getValue());
    $newValue = NULL;
    switch ($fieldDefinition->getType()) {
      case 'boolean':
        $map = [1, 0];
        $value = $fieldItemList->getValue();
        $newValue = $hasValue ? $map[$value[0]['value']] : rand(0, 1);
        $entity->set($fieldName, $newValue);
        break;

      case 'integer':
        $newValue = $hasValue ? $fieldItemList->value + 1 : rand(0, 1);
        $entity->set($fieldName, $newValue);
        break;

      case 'float':
        $newValue = $hasValue ? $fieldItemList->value + 1 : rand(0, 1);
        $entity->set($fieldName, $newValue);
        break;

      case 'datetime':
        $newValue = $hasValue
          ? date(DateTimeItemInterface::DATE_STORAGE_FORMAT, time())
          :  date(DateTimeItemInterface::DATE_STORAGE_FORMAT, time() - 86400);
        break;

      case 'string':
        $newValue = $hasValue
          ? 'Lorem ipsum dolor sit amet'
          : 'Curabitur lobortis pellentesque nisl';
        break;

      case 'string_long':
        $newValue = $hasValue
          ? 'Suspendisse vitae fringilla augue. Proin eros eros, eleifend nec mi eget, hendrerit gravida libero. Curabitur lobortis pellentesque nisl, vitae mattis mauris pulvinar at. Vestibulum luctus mauris a quam ultrices sagittis. Vivamus laoreet erat sed lorem tincidunt elementum.'
          : 'Mauris tristique enim lectus, et posuere felis consequat eget. Nunc at enim id diam elementum laoreet. Nulla facilisi. Nam eros leo, dignissim eget posuere quis, bibendum vel tellus.';
        break;

      case 'text_long':
        $newValue = $hasValue
          ? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas tincidunt nisi nulla, eu pretium ligula vehicula viverra. Sed risus est, pharetra at congue at, consectetur vel nibh. Duis sit amet sagittis tortor, non consequat tellus. Nam vestibulum enim felis, quis pellentesque erat molestie a. Nullam ultrices sapien suscipit nulla dapibus, et congue erat pharetra. Pellentesque consequat semper felis ac posuere. Suspendisse ut ultricies ex. Nullam cursus ligula in odio tincidunt ultrices. Donec sed suscipit nibh.'
          : 'Cras ut dui sem. Integer pretium, augue commodo hendrerit dignissim, erat mauris faucibus velit, nec efficitur felis tellus et mi. Nulla sed consequat augue. Cras ut enim mollis enim feugiat suscipit fermentum sed massa. Aenean at nulla sit amet turpis interdum euismod ac ac libero. Sed sapien mauris, vulputate vitae justo id, sodales dignissim enim.';
        break;

      case 'list_string':
        $allowedValues = $fieldStorageDefinition->getSettings()['allowed_values'];
        if ($hasValue) {
          end($allowedValues);
        }
        $newValue = key($allowedValues);
        break;

      case 'entity_reference':
        $entityTypeManager = \Drupal::entityTypeManager();
        $handlerSettings = $fieldDefinition->getSetting('handler_settings');
        $targetType = $fieldDefinition->getSetting('target_type');
        $entityType = $entityTypeManager->getDefinition($targetType);
        $entityStorage = $entityTypeManager->getStorage($targetType);
        $query = $entityStorage->getQuery();

        if (!empty($handlerSettings['target_bundles'])) {
          $targetBundle = reset($handlerSettings['target_bundles']);
          $query->condition($entityType->getKey('bundle'), $targetBundle);
        }

        $ids = $query->execute();
        if ($hasValue) {
          end($ids);
        }
        $newValue = key($ids);
        $oldValue = $fieldItemList->getValue();
        if (!empty($oldValue[0]['target_id']) && $oldValue[0]['target_id'] == $newValue) {
          reset($ids);
          $newValue = key($ids);
        }

        if (in_array($fieldName, ['field_as_threats_categories', 'field_as_benefits_category']) && empty(Term::load($newValue)->parent->target_id)) {
          $sampleChild = self::createSampleEntity($targetType, $targetBundle, ['parent' => $newValue, 'name' => "{$newValue} subcategory"]);
          $newValue = [$newValue, $sampleChild->id()];
        }

        break;

      case 'entity_reference_revisions':
        $handlerSettings = $fieldDefinition->getSetting('handler_settings');
        $targetType = $fieldDefinition->getSetting('target_type');
        $targetBundle = reset($handlerSettings['target_bundles']);

        $cardinality = $fieldStorageDefinition->getCardinality();
        if ($cardinality == -1) {
          $cardinality = $maxChildParagraphs;
        }

        // Todo check set vs appendItem.
        $count = $entity->get($fieldName)->count();
        for ($i = 0; $i < $cardinality; $i++) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
          $childEntity = self::createSampleEntity($targetType, $targetBundle);
          self::populateAllFieldsData($childEntity, $maxChildParagraphs);
          $childEntity->save();
          if ($i < $count) {
            $entity->get($fieldName)->set($i, $childEntity);
          }
          else {
            $entity->get($fieldName)->appendItem($childEntity);
          }
        }
        break;
    }
    if (!empty($newValue)) {
      $entity->set($fieldName, $newValue);
    }
  }

  public static function createSampleEntity($type, $bundle, $fields = []) {
    $entityTypeManager = \Drupal::entityTypeManager();
    $entityType = $entityTypeManager->getDefinition($type);
    $entityStorage = $entityTypeManager->getStorage($type);
    $entity = $entityStorage->create([
      $entityType->getKey('bundle') => $bundle,
    ]);
    foreach ($fields as $field => $value) {
      $entity->set($field, $value);
    }
    $entity->save();
    return $entity;
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

}
