<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\node\Entity\Node;
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
  public static function createAllTestData() {
    self::createUsers();
    self::createTaxonomyTerms();
    foreach (self::getAssessments() as $title) {
      self::createAssessment($title);
    }
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
    $vocabularies = Vocabulary::loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      for ($i = 1; $i <= 5; $i++) {
        $term = Term::create([
          'vid' => $vocabulary->id(),
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
   * Create an site_assessment node.
   *
   * @param null $title
   *
   * @return \Drupal\node\NodeInterface
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
