<?php

namespace Drupal\iucn_assessment\Tests;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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
  const COORDINATOR1 = 'coord1@test.ro';
  const COORDINATOR2 = 'coord2@test.ro';

  // Assessors.
  const ASSESSOR1 = 'assessor1@test.ro';
  const ASSESSOR2 = 'assessor2@test.ro';

  // Reviewers.
  const REVIEWER1 = 'rev1@test.ro';
  const REVIEWER2 = 'rev2@test.ro';
  const REVIEWER3 = 'rev3@test.ro';

  // Assessments.
  const ASSESSMENT1 = 'assessment1';

  /**
   * Create all the test data.
   */
  public static function createAllTestData() {
    self::createUsers();
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
      self::ASSESSMENT1 => [
        'field_state' => 'assessment_new',
        'langcode' => 'en',
      ],
    ];
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
   * Create an site_assessment node.
   *
   * @param string $title
   *   The title.
   * @param string $state
   *   The initial field_state.
   * @param string $langcode
   *   The langcode.
   *
   * @return int
   *   The node id.
   */
  public static function createAssessment($title = NULL, $state = 'assessment_new', $langcode = 'en') {
    if (empty($title)) {
      $title = 'test assessment';
    }
    $assessment = [
      'type' => 'site_assessment',
      'title' => $title,
      'langcode' => $langcode,
      'created' => time(),
      'uid' => 0,
      'promote' => 0,
      'status' => 0,
//      'field_state' => $state,
    ];#->
    $node = Node::create($assessment);
    $node->save();
    return $node->id();
  }

  /**
   * Create all the test assessments.
   */
  public static function createAssessments() {
    foreach (self::getAssessments() as $title => $data) {
      $state = !empty($data['state']) ? $data['state'] : NULL;
      $langcode = !empty($data['langcode']) ? $data['langcode'] : NULL;
      self::createAssessment($title, $state, $langcode);
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
    if ($bundle) {
      $query->condition('type', $bundle);
    }
    $nids = $query->execute();
    if (!empty($nids)) {
      return Node::load(current($nids));
    }
    return NULL;
  }

}
