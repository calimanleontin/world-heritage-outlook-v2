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

  private $site;

  public static $modules = ['iucn_who_structure'];


  public function setUp() {
    parent::setUp();
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    $this->site = $this->createSite([
      'assessment_conservation_rating' => SiteStatus::IUCN_OUTLOOK_STATUS_CRITICAL,
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);
  }


  private function createSite($values = []) {
    $site = null;
    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => $values['assessment_conservation_rating'],
    ]);
    $status->save();
    $this->assertTrue($status->id() > 0, 'conservation status term created');

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
      'field_as_global_assessment_level' => ['target_id' => $status->id()],
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
    $status = SiteStatus::getOverallAssessmentLevel($this->site);
    $this->assertTrue(!empty($status), 'global status is valid');
    $this->assertEqual('critical', $status->label());
  }


  public function testGetOverallProtectionLevel() {
    $status = SiteStatus::getOverallProtectionLevel($this->site);
    $this->assertTrue(!empty($status), 'protection status is valid');
    $this->assertEqual('protected', $status->label());
  }


  public function testGetOverallThreatLevel() {
    $status = SiteStatus::getOverallThreatLevel($this->site);
    $this->assertTrue(!empty($status), 'threat status is valid');
    $this->assertEqual('threatened', $status->label());
  }


  public function testGetOverallValuesLevel() {
    $status = SiteStatus::getOverallValuesLevel($this->site);
    $this->assertTrue(!empty($status), 'value status is valid');
    $this->assertEqual('valued', $status->label());
  }
}
