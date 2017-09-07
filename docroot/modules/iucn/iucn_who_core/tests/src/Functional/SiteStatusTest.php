<?php

/**
 * @file Contains the test for \Drupal\iucn_who_core\SiteStatus class
 */

namespace Drupal\Tests\iucn_who_core\Functional;


use Drupal\iucn_who_core\SiteStatus;
use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;
use Drupal\taxonomy\Entity\Term;


/**
 * Class SiteStatusTest
 *
 * @package Drupal\Tests\iucn_who_core
 * @group iucn
 */
class SiteStatusTest extends WebTestBase {

  protected $strictConfigSchema = false;
  protected $profile = 'iucn_test';

  public static $modules = ['iucn_who_structure'];


  /** @var \Drupal\taxonomy\TermInterface */
  private $status_good = null;
  /** @var \Drupal\taxonomy\TermInterface */
  private $status_good_concerns = null;
  /** @var \Drupal\taxonomy\TermInterface */
  private $status_significant_concerns = null;
  /** @var \Drupal\taxonomy\TermInterface */
  private $status_critical = null;

  public function setUp() {
    parent::setUp();
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();
    $this->createConservationRatings();
  }


  private function createConservationRatings() {
    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_GOOD,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_GOOD ]
    ]);
    $status->save();
    $this->status_good = $status;
    $this->assertEqual(1, $status->id(),
      SiteStatus::IUCN_OUTLOOK_STATUS_GOOD . ' conservation status term created'
    );

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS ]
    ]);
    $status->save();
    $this->status_good_concerns = $status;
    $this->assertEqual(2, $status->id(),
      SiteStatus::IUCN_OUTLOOK_STATUS_GOOD_CONCERNS . ' conservation status term created'
    );

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS ]
    ]);
    $status->save();
    $this->status_significant_concerns = $status;
    $this->assertEqual(3, $status->id(),
      SiteStatus::IUCN_OUTLOOK_STATUS_SIGNIFICANT_CONCERNS . ' conservation status term created'
    );

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_CRITICAL,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_CRITICAL ]
    ]);
    $status->save();
    $this->status_critical = $status;
    $this->assertEqual(4, $status->id(),
      SiteStatus::IUCN_OUTLOOK_STATUS_CRITICAL . ' conservation status term created'
    );
  }


  private function createSite($values = []) {
    $site = null;

    $protection = Term::create([
      'vid' => 'assessment_protection_rating',
      'name' => $values['assessment_protection_rating']
    ]);
    $protection->save();
    $this->assertTrue($protection->id() > 0, 'protection level term created');

    $threat = Term::create([
      'vid' => 'assessment_threat_level',
      'name' => $values['assessment_threat_level'],
    ]);
    $threat->save();
    $this->assertTrue($threat->id() > 0, 'threat level term created');

    $value = Term::create([
      'vid' => 'assessment_value_state',
      'name' => $values['assessment_value_state'],
    ]);
    $value->save();
    $this->assertTrue($value->id() > 0, 'value level term created');

    $assessment = Node::create([
      'type' => 'site_assessment',
      'title' => 'test assessment',
      'field_as_global_assessment_level' => ['target_id' => $values['assessment_conservation_rating']],
      'field_as_protection_ov_rating' => ['target_id' => $protection->id()],
      'field_as_threats_rating' => ['target_id' => $threat->id()],
      'field_as_vass_wh_state' => ['target_id' => $value->id()],
    ]);
    $assessment->save();
    $this->assertTrue($assessment->id() > 0, 'assessment created');

    $site = Node::create([
      'type' => 'site',
      'title' => 'test site',
      'field_current_assessment' => ['target_id' => $assessment->id()],
    ]);
    $site->save();
    $this->assertTrue($site->id() > 0, 'site created');
    return $site;
  }


  public function testGetOverallAssessmentLevel() {
    $site = $this->createSite([
      'assessment_conservation_rating' => $this->status_critical->id(),
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);
    $status = SiteStatus::getOverallAssessmentLevel($site);
    $this->assertTrue(!empty($status), 'global status is valid');
    $this->assertEqual('critical', $status->label());
  }


  public function testGetOverallProtectionLevel() {
    $site = $this->createSite([
      'assessment_conservation_rating' => $this->status_critical->id(),
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);
    $status = SiteStatus::getOverallProtectionLevel($site);
    $this->assertTrue(!empty($status), 'protection status is valid');
    $this->assertEqual('protected', $status->label());
  }


  public function testGetOverallThreatLevel() {
    $site = $this->createSite([
      'assessment_conservation_rating' => $this->status_critical->id(),
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);
    $status = SiteStatus::getOverallThreatLevel($site);
    $this->assertTrue(!empty($status), 'threat status is valid');
    $this->assertEqual('threatened', $status->label());
  }


  public function testGetOverallValuesLevel() {
    $site = $this->createSite([
      'assessment_conservation_rating' => $this->status_critical->id(),
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);
    $status = SiteStatus::getOverallValuesLevel($site);
    $this->assertTrue(!empty($status), 'value status is valid');
    $this->assertEqual('valued', $status->label());
  }


  public function testGetSitesStatusStatistics() {
    $statuses = [
      $this->status_good->id(),
      $this->status_good->id(),
      $this->status_good->id(),
      $this->status_good->id(),
      $this->status_good_concerns->id(),
      $this->status_good_concerns->id(),
      $this->status_good_concerns->id(),
      $this->status_significant_concerns->id(),
      $this->status_significant_concerns->id(),
      $this->status_critical->id(),
    ];
    foreach ($statuses as $status) {
      $this->createSite([
        'assessment_conservation_rating' => $status,
        'assessment_protection_rating' => 'protected',
        'assessment_threat_level' => 'threatened',
        'assessment_value_state' => 'valued'
      ]);
    }

    $statistics = SiteStatus::getSitesStatusStatistics();
    $this->assertEqual(40, $statistics[$this->status_good->id()]);
    $this->assertEqual(30, $statistics[$this->status_good_concerns->id()]);
    $this->assertEqual(20, $statistics[$this->status_significant_concerns->id()]);
    $this->assertEqual(10, $statistics[$this->status_critical->id()]);
  }
}
