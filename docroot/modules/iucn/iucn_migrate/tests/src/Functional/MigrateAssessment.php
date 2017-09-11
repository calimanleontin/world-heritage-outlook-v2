<?php

namespace Drupal\Tests\iucn_migrate\Functional;

//use Drupal\simpletest\WebTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Class MigrateAssessment
 *
 * @package Drupal\Tests\iucn_migrate\Functional
 * @group iucn
 */
class MigrateAssessment extends BrowserTestBase {
  use MigrateTrait;

  protected $strictConfigSchema = false;

  protected static $modules = [
    'block',
    'breakpoint',
    'layout_discovery',
    'ds',
    'entity_reference_revisions',
    'migrate_api',
    'migrate_plus',
    'migrate_tools',
    'iucn_who_structure',
    'iucn_site',
    'iucn_assessment',
    'iucn_migrate',
  ];

  protected $adminUser;

  protected $profile = 'iucn_test';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the users used for the tests.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer nodes',
      'access content overview',
      'administer content types',
      'administer content translation',
      'administer languages',
      'administer migrations',
      'bypass node access',
    ]);

    $this->drupalLogin($this->adminUser);

    // Fix for creating translation columns in node table.
//    $this->drupalGet('/admin/config/regional/content-language');
//    $this->click('[type="submit"]');
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    // Set migration source base url.
    $iucn_settings = \Drupal::configFactory()->getEditable('iucn_migration.settings');
    $iucn_settings->set('assessment_path', $this->baseUrl . '/modules/iucn/iucn_migrate/source/assessments.json');
    $iucn_settings->save();

    drupal_flush_all_caches();

    /* @var \Drupal\migrate\Plugin\MigrationPluginManager $service */
    $service = \Drupal::service('plugin.manager.migration');
    $service->clearCachedDefinitions();

    $this->drupalGet('/admin/structure/migrate/manage/assessments/migrations');

    \Drupal\iucn_assessment\WHOTaxonomiesInstaller::install();

    // Add a site.
    $form = [
      'field_site_id[0][value]' => 91,
      'field_wdpa_id[0][value]' => 1,
      'field_unesco_id[0][value]' => 1,
      'title[0][value]' => 'New site',
      'field_danger_list[0][subform][field_status]' => 'inscribed',
    ];
    $this->drupalPostForm('/node/add/site', $form, 'Save and publish');
    $this->assertSession()->pageTextContains('New site');

  }

  /**
   * Tests full migration for an assessment.
   */
  public function testFullImport() {
    $this->drupalLogin($this->adminUser);

    $migration_ids = [
      'assessments_paragraphs',
      'assessments_paragraphs_revisions',
      'assessments_paragraphs_translations',
      'assessments',
      'assessments_revisions',
      'assessments_translations',
      'assessments_paragraphs_revisions',
      'assessments_paragraphs_translations',
      'assessments_revisions',
    ];

    $this->migrate($migration_ids);

    drupal_flush_all_caches();

    // Test that node was created.
    $node = Node::load(2);
    $this->assertTrue(is_object($node));

    // Test title.
    $this->assertEquals($node->label(), '2014 New site');

    $this->drupalGet('/node/2');
    $this->drupalGet('/admin/structure/migrate/manage/assessments/migrations/assessments_revisions/messages');
    $this->drupalGet('/admin/structure/migrate/manage/assessments/migrations/assessments_translations/messages');
    $this->drupalGet('/admin/structure/migrate/manage/assessments/migrations');

    // Test revisions created.
    $vids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);
    $this->assertEquals(count($vids), 2);

    /* @var \Drupal\node\Entity\Node $first_revision */
    $first_revision = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($vids[0]);

    /* @var \Drupal\node\Entity\Node $second_revision */
    $second_revision = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($vids[1]);

    // Integrity asserts.

    // Check versions.
    $this->assertEquals($first_revision->field_as_version->value, '0.1');
    $this->assertEquals($second_revision->field_as_version->value, '0.2');
    // Check default revision to be the latest.
    $this->assertTrue($second_revision->isDefaultRevision());

    // Check that new paragraph in latest revision doesn't show in old revision
    // but shows in the second.
    $this->assertEquals($first_revision->field_as_values_wh->count(), 3);
    $this->assertEquals($second_revision->field_as_values_wh->count(), 4);

    // Check that a paragraph removed in latest version shows in first
    // but not in latest.
    $this->assertEquals($first_revision->field_as_projects->count(), 3);
    $this->assertEquals($second_revision->field_as_projects->count(), 2);
    $this->assertEquals(
      $first_revision->field_as_projects->get(0)->entity->field_as_description->value,
      'activeConservationProjects description 1');
    $this->assertEquals(
      $second_revision->field_as_projects->get(0)->entity->field_as_description->value,
      '2 activeConservationProjects description 1');


    // Data equals asserts.
    // Values.
    $this->assertEquals(
      $first_revision->field_as_values_wh->get(0)->entity->field_as_values_value->value,
      'assessmentWhvalues values 1');
    $this->assertEquals(
      $first_revision->field_as_values_wh->get(0)->entity->field_as_description->value,
      'assessmentWhvalues description 1');
//    $this->assertEquals(
//      $first_revision->field_as_values_wh->get(0)->entity->field_as_values_criteria->entity->label(),
//      'vii');
    $this->assertEquals(
      $first_revision->field_as_values_wh->get(0)->entity->field_as_values_curr_text->value,
      'currentStateTrend justification 1');
    $this->assertEquals(
      $first_revision->field_as_values_wh->get(0)->entity->field_as_values_curr_state->entity->label(),
      'Low Concern');
    $this->assertEquals(
      $first_revision->field_as_values_wh->get(0)->entity->field_as_values_curr_trend->entity->label(),
      'Deteriorating');

    $this->assertEquals(
      $first_revision->field_as_values_wh->get(1)->entity->field_as_values_value->value,
      'assessmentWhvalues values 2');


    $this->assertEquals(
      $first_revision->field_as_values_bio->get(0)->entity->field_as_values_value->value,
      'biodiversityValues value 1');
    $this->assertEquals(
      $first_revision->field_as_values_bio->get(0)->entity->field_as_description->value,
      'biodiversityValues description 1');
    $this->assertEquals(
      $first_revision->field_as_values_bio->get(0)->entity->field_as_values_curr_text->value,
      'currentStateTrend justification 10');
    $this->assertEquals(
      $first_revision->field_as_values_bio->get(0)->entity->field_as_values_curr_state->entity->label(),
      'Low Concern');
    $this->assertEquals(
      $first_revision->field_as_values_bio->get(0)->entity->field_as_values_curr_trend->entity->label(),
      'Deteriorating');

    $this->assertEquals(
      $first_revision->field_as_values_bio->get(1)->entity->field_as_values_value->value,
      'biodiversityValues value 2');


    // Second revision.

    // Values.
    $this->assertEquals(
      $second_revision->field_as_values_wh->get(0)->entity->field_as_values_value->value,
      '2 assessmentWhvalues values 1');

    $this->assertEquals(
      $second_revision->field_as_values_wh->get(3)->entity->field_as_values_value->value,
      '2 assessmentWhvalues values 4');

    // TODO more asserts


    // Translations.

    // Values.

    $translation = $node->getTranslation('fr');

    $this->assertEquals(
      $translation->field_as_values_wh->get(0)->entity->getTranslation('fr')->field_as_values_value->value,
      '3 fr assessmentWhvalues values 1');


    $this->assertEquals(
      $translation->field_as_values_wh->get(1)->entity->getTranslation('fr')->field_as_values_value->value,
      '3 fr assessmentWhvalues values 2');

    // TODO more asserts


    // TODO Test migration without --update for a new version. (assessment_update.json)

    // TODO Test migration without --update for a new translation. (assessment_update_es.json)

  }

}
