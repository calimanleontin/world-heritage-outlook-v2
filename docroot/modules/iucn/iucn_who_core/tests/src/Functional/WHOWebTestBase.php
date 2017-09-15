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
 * Class WHOBaseWebTestBase for WHO tests with utility methods.
 *
 * @package Drupal\Tests\iucn_who_core
 * @group iucn
 */
class WHOWebTestBase extends WebTestBase {

  protected $strictConfigSchema = FALSE;

  protected $profile = 'iucn_test';

  public static $modules = ['iucn_who_structure'];


  /** @var \Drupal\taxonomy\TermInterface */
  public $status_good = null;
  /** @var \Drupal\taxonomy\TermInterface */
  public $status_good_concerns = null;
  /** @var \Drupal\taxonomy\TermInterface */
  public $status_significant_concerns = null;
  /** @var \Drupal\taxonomy\TermInterface */
  public $status_critical = null;
  /** @var \Drupal\taxonomy\TermInterface */
  public $status_coming_soon = null;


  public function setUp() {
    parent::setUp();
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();
  }


  public function createConservationRatings() {
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

    $status = Term::create([
      'vid' => 'assessment_conservation_rating',
      'name' => SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON,
      'field_css_identifier' => ['value' => SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON ]
    ]);
    $status->save();
    $this->status_coming_soon = $status;
    $this->assertEqual(4, $status->id(),
      SiteStatus::IUCN_OUTLOOK_STATUS_DATA_COMING_SOON . ' conservation status term created'
    );
  }



  public function createSite($values = []) {
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

}