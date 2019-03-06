<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * @group iucn_assessment_forms
 */
class CommentsTest extends IucnAssessmentTestBase {

  /**
   * Check comments.
   */
  protected function testComments() {
    $assessment = $this->getNodeByTitle(TestSupport::ASSESSMENT1);
    /** @var \Drupal\user\Entity\User $assessor1 */
    $assessor = user_load_by_mail(TestSupport::ASSESSOR1);

    $assessment_threat_level = Term::create([
      'name' => 'Threat level',
      'vid' => 'assessment_threat_level',
    ]);
    $assessment_threat_level->save();

    $assessment_protection_rating = Term::create([
      'name' => 'Protection rating',
      'vid' => 'assessment_protection_rating',
    ]);
    $assessment_protection_rating->save();

    $assessment_value_state = Term::create([
      'name' => 'Value state',
      'vid' => 'assessment_value_state',
    ]);
    $assessment_value_state->save();

    $assessment_value_trend = Term::create([
      'name' => 'Value trend',
      'vid' => 'assessment_value_trend',
    ]);
    $assessment_value_trend->save();

    $assessment_conservation_rating = Term::create([
      'name' => 'Conservation rating',
      'vid' => 'assessment_conservation_rating',
    ]);
    $assessment_conservation_rating->save();

    $as_site_value_wh_paragraph = Paragraph::create(['type' => 'as_site_value_wh']);
    $as_site_value_wh_paragraph->save();

    $as_site_threat_paragraph = Paragraph::create(['type' => 'as_site_threat']);
    $as_site_threat_paragraph->save();

    $as_site_protection_paragraph = Paragraph::create(['type' => 'as_site_protection']);
    $as_site_protection_paragraph->save();

    $as_site_reference_paragraph = Paragraph::create(['type' => 'as_site_reference']);
    $as_site_reference_paragraph->save();

    $assessment->field_as_values_wh->appendItem($as_site_value_wh_paragraph);
    $assessment->field_as_threats_current->appendItem($as_site_threat_paragraph);
    $assessment->field_as_threats_current_text->value = 'text';
    $assessment->field_as_threats_potent_text->value = 'text';
    $assessment->field_as_threats_text->value = 'text';
    $assessment->field_as_threats_current_rating->target_id = $assessment_threat_level->id();
    $assessment->field_as_threats_potent_rating->target_id = $assessment_threat_level->id();
    $assessment->field_as_threats_rating->target_id = $assessment_threat_level->id();
    $assessment->field_as_protection->appendItem($as_site_protection_paragraph);
    $assessment->field_as_protection_ov_text->value = 'text';
    $assessment->field_as_protection_ov_rating->target_id = $assessment_protection_rating->id();
    $assessment->field_as_protection_ov_out_text->value = 'text';
    $assessment->field_as_protection_ov_out_rate->target_id = $assessment_protection_rating->id();
    $assessment->field_as_vass_wh_text->value = 'text';
    $assessment->field_as_vass_wh_state->target_id = $assessment_value_state->id();
    $assessment->field_as_vass_wh_trend->target_id = $assessment_value_trend->id();
    $assessment->field_as_global_assessment_text->value = 'text';
    $assessment->field_as_global_assessment_level->target_id = $assessment_conservation_rating->id();
    $assessment->field_as_references_p->appendItem($as_site_reference_paragraph);
    $assessment->save();

    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_NEW);
    $this->setAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, ['field_assessor' => $assessor->id()]);

    $this->userLogIn(TestSupport::ASSESSOR1);

    $tabs = [
      'values',
      'threats',
      'protection-management',
      'assessing-values',
      'conservation-outlook',
      'benefits',
      'projects',
      'references',
    ];

    foreach ($tabs as $tab) {
      $comment_text = 'test comment ' . $tab;
      $url = $assessment->toUrl('edit-form', ['query' => ['tab' => $tab]]);
      $this->drupalPostForm($url, ["comment_$tab" => $comment_text], t('Save'));
      $this->assertSession()->responseContains('has been updated.');
      $this->drupalGet($url);
      $this->assertSession()->pageTextContains($comment_text);
    }
  }
}
