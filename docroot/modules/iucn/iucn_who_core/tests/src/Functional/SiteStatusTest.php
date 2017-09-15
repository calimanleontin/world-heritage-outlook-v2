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
class SiteStatusTest extends WHOWebTestBase {

  public function setUp() {
    parent::setUp();
    $this->createConservationRatings();
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

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON]
    ]);
    $status->save();
    $this->assertTrue(!empty($status), SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON . ' status is valid');

    $site = Node::create([
      'type' => 'site',
      'title' => 'test site',
    ]);
    $site->setPublished();
    $site->save();
    $status = SiteStatus::getOverallAssessmentLevel($site);
    $this->assertEqual(SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON, $status->label());
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
      $this->status_coming_soon->id(),
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
