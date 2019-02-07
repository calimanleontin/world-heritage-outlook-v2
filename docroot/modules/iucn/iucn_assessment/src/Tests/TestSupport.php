<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

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


  /**
   * Create all the test data.
   */
  public static function createAllTestData() {
    self::createUsers();
    self::createTaxonomyTerms();
    self::createAssessments();
  }

  /**
   * Gets an array.
   *
   * The keys inside the array represent an user email.
   * The values are arrays of user roles associated with the key user.
   *
   * @return array
   *   The users with their roles.
   */
  public static function getUsers() {
    return [
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
  }

  /**
   * An array containing all the site assessments.
   *
   * @return array
   *   The assessments.
   */
  public static function getAssessments() {
    return [
      self::ASSESSMENT1,
      self::ASSESSMENT2,
      self::ASSESSMENT3,
      self::ASSESSMENT4,
    ];
  }

  /**
   * Generate 5 terms in each important vocabulary.
   */
  public static function createTaxonomyTerms() {
    $vocabularies = [
      'assessment_threat_level',
      'assessment_protection_rating',
      'assessment_value_state',
      'assessment_value_trend',
      'assessment_conservation_rating',
    ];

    foreach ($vocabularies as $vocabulary) {
      for ($i = 1; $i <= 5; $i++) {
        $term = Term::create([
          'vid' => $vocabulary,
          'name' => "{$vocabulary} term {$i}",
        ]);
        $term->save();
      }
    }
  }

  /**
   * Generate all the users required for testing.
   */
  public static function createUsers() {
    foreach (self::getUsers() as $user => $roles) {
      self::createUser($user, $roles);
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
   * Create a site_assessment node.
   *
   * @param null $title
   *
   * @return \Drupal\node\NodeInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createAssessment($title = NULL) {
    $node = Node::create([
      'type' => 'site_assessment',
      'title' => $title ?: 'Test assessment',
      'created' => time(),
      'uid' => 0,
      'promote' => 0,
      'field_state' => 'assessment_creation',
      'status' => 0,
      'field_as_version' => 1,
      'field_as_cycle' => 2020,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Create all the test assessments.
   */
  public static function createAssessments() {
    foreach (self::getAssessments() as $title) {
      self::createAssessment($title);
    }
  }

  /**
   * Find node by title.
   *
   * @param string $title
   *   Node title.
   * @param string $bundle
   *   Node type.
   *
   * @return \Drupal\node\NodeInterface
   *   Created node entity
   */
  public static function getNodeByTitle($title, $bundle = NULL) {
    $query = \Drupal::entityQuery('node');
    $query->condition('title', $title);
    if (!empty($bundle)) {
      $query->condition('type', $bundle);
    }
    $ids = $query->execute();
    return !empty($ids)
      ? Node::load(current($ids))
      : NULL;
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
