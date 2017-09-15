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
 * Class SitesQueryUtilTest
 *
 * @package Drupal\Tests\iucn_who_core
 * @group iucn
 */
class SitesQueryUtilTest extends WHOWebTestBase {


  public function testGetPublishedSitesWithAssessments() {
    $site1 = $this->createSite([
      'assessment_conservation_rating' => $this->status_critical->id(),
      'assessment_protection_rating' => 'protected',
      'assessment_threat_level' => 'threatened',
      'assessment_value_state' => 'valued'
    ]);

  }

}