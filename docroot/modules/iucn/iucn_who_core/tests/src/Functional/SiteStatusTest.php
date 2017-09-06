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

  private $assessment;
  private $site;

  public static $modules = ['iucn_who_structure'];


  public function setUp() {
    parent::setUp();
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => 'critical'
    ]);
    $status->save();
    $this->assertEqual(1, $status->id(), 'conservation status term created');

    $protection = Term::create([
      'vid' => 'assessment_protection_rating',
      'name' => 'protected'
    ]);
    $protection->save();
    $this->assertEqual(2, $protection->id(), 'protection level term created');

    $threat = Term::create([
      'vid' => 'assessment_threat_level',
      'name' => 'threatened'
    ]);
    $threat->save();
    $this->assertEqual(3, $threat->id(), 'threat level term created');

    $value = Term::create([
      'vid' => 'assessment_value_state',
      'name' => 'valued'
    ]);
    $value->save();
    $this->assertEqual(4, $value->id(), 'value level term created');

    $this->assessment = Node::create([
      'type' => 'site_assessment',
      'title' => 'test assessment',
      'field_as_global_assessment_level' => ['target_id' => $status->id()],
      'field_as_protection_ov_rating' => ['target_id' => $protection->id()],
      'field_as_threats_rating' => ['target_id' => $threat->id()],
      'field_as_vass_wh_state' => ['target_id' => $value->id()],
    ]);
    $this->assessment->save();
    $this->assertEqual(1, $this->assessment->id(), 'assessment created');

    $this->site = Node::create([
      'type' => 'site',
      'title' => 'test site',
      'field_current_assessment' => ['target_id' => $this->assessment->id()],
    ]);
    $this->site->save();
    $this->assertEqual(2, $this->site->id(), 'site created');
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
