<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Language\LanguageInterface;

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
    self::createAssessments();
    foreach (self::getVocabularies() as $vocabulary) {
      self::createTerm($vocabulary, $vocabulary->get('vid') . '_term1');
      self::createTerm($vocabulary, $vocabulary->get('vid') . '_term2');
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
   * An array containing all the vocabularies necessary for unit tests.
   *
   * @return array
   *   The vocabularies.
   */
  public static function getVocabularies() {
    $vocabularies = Vocabulary::loadMultiple();
    return $vocabularies;
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
   * Returns a new term with random properties in vocabulary $vid.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   The vocabulary object
   * @param string $name
   *   The name given to the taxonomy term.
   * @param array $values
   *   (optional) An array of values to set, keyed by property name. If the
   *   entity type has bundles, the bundle key has to be specified.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The new taxonomy term object.
   */
  public static function createTerm(\Drupal\taxonomy\Entity\Vocabulary $vocabulary, $name, $values = []) {
    $filter_formats = filter_formats();
    $format = array_pop($filter_formats);
    $term = Term::create($values + [
        'name' => $name,
        'description' => [
          'value' => 'Test description.',
          // Use the first available text format.
          'format' => $format->id(),
        ],
        'vid' => $vocabulary->id(),
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ]);
    $term->save();
    return $term;
  }

  /**
   * Create an site_assessment node.
   *
   * @param string $title
   *   The title.
   *
   * @return int
   *   The node id.
   */
  public static function createAssessment($title = NULL) {
    if (empty($title)) {
      $title = 'test assessment';
    }
    $assessment = [
      'type' => 'site_assessment',
      'title' => $title,
      'created' => time(),
      'uid' => 0,
      'promote' => 0,
      'field_state' => 'assessment_creation',
      'status' => 0,
      'field_as_version' => 1,
      'field_as_cycle' => 2020,
    ];
    $node = Node::create($assessment);
    $node->save();

    return $node->id();
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
    if ($bundle) {
      $query->condition('type', $bundle);
    }
    $nids = $query->execute();
    if (!empty($nids)) {
      return Node::load(current($nids));
    }
    return NULL;
  }

  /**
   * Utility: find term by name and vid.
   * @param null $name
   *  Term name
   * @param null $vid
   *  Term vid
   * @return int
   *  Term id or 0 if none.
   */
  public static function getTidByName($name = NULL, $vid = NULL) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    $entityManager = \Drupal::service('entity.manager');
    $terms = $entityManager->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);

    return !empty($term) ? $term->id() : 0;
  }

}
