<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

/**
 * @group edw
 * @group edwBrowser
 * @group assessmentForms
 */
class ChangeStateFormTest extends WorkflowTestBase {

  const REQUIRED_PARAGRAPH_FIELDS = [
    'field_as_benefits' => [
      'field_as_benefits_category',
      'field_as_description',
    ],
    'field_as_projects' => [
      'field_as_description',
      'field_as_projects_organisation',
    ],
    'field_as_protection' => [
      'field_as_description',
      'field_as_protection_rating',
      'field_as_protection_topic',
    ],
    'field_as_references_p' => [
      'field_reference',
    ],
    'field_as_threats_current' => [
      'field_as_description',
      'field_as_threats_categories',
      'field_as_threats_rating',
      'field_as_threats_threat',
    ],
    'field_as_threats_potential' => [
      'field_as_description',
      'field_as_threats_categories',
      'field_as_threats_rating',
      'field_as_threats_threat',
    ],
    'field_as_values_bio' => [
      'field_as_description',
      'field_as_values_value',
    ],
    'field_as_values_wh' => [
      'field_as_description',
      'field_as_values_criteria',
      'field_as_values_curr_state',
      'field_as_values_curr_text',
      'field_as_values_curr_trend',
      'field_as_values_value',
    ],
  ];

  const REQUIRED_FIELDS = [
    'field_as_global_assessment_level',
    'field_as_global_assessment_text',
    'field_as_protection_ov_out_rate',
    'field_as_protection_ov_out_text',
    'field_as_protection_ov_rating',
    'field_as_protection_ov_text',
    'field_as_threats_current_rating',
    'field_as_threats_current_text',
    'field_as_threats_potent_rating',
    'field_as_threats_potent_text',
    'field_as_threats_rating',
    'field_as_threats_text',
    'field_as_vass_wh_state',
    'field_as_vass_wh_text',
    'field_as_vass_wh_trend',
    'field_as_references_p',
    'field_as_threats_current',
    'field_as_values_wh',
    'field_as_benefits_summary',
    'field_as_vass_bio_text',
    'field_as_vass_bio_state',
    'field_as_vass_bio_trend',
  ];

  const CATEGORY_FIELDS = [
    'field_as_benefits' => 'field_as_benefits_category',
    'field_as_threats_current' => 'field_as_threats_categories',
    'field_as_threats_potential' => 'field_as_threats_categories',
  ];

  const REQUIRED_DEPENDENT_FIELDS = [
    'field_as_legality' => [
      'subcategories' => [
        1384, // Hunting and trapping
        1386, // Logging/ Wood harvesting
        1387, // Fishing/ Harvesting aquatic resources
        1388, // Other biological resource use
        1433, // Non-timber forest products (NTFPs)
      ],
      'message' => 'Legality field is required',
    ],
  ];

  public function testValidation() {
    // Create valid assessment.
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    $this->userLogIn(TestSupport::COORDINATOR1);
    foreach (self::REQUIRED_FIELDS as $field) {
      $assessment->get($field)->setValue(NULL);
    }
    $assessment->save();

    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($stateChangeForm);

    $this->assertSession()
      ->pageTextContains('Global assessment level field is required.');
    $this->assertSession()
      ->pageTextContains('Assessment of Conservation Outlook field is required.');
    $this->assertSession()
      ->pageTextContains('Protection overall rating outside factors field is required.');
    $this->assertSession()
      ->pageTextContains('Overall assessment of protection and management field is required.');
    $this->assertSession()
      ->pageTextContains('Protection overall rating field is required.');
    $this->assertSession()
      ->pageTextContains('Assessment of the effectiveness of protection and management in addressing threats outside the site field is required.');
    $this->assertSession()->pageTextContains('References field is required.');
    $this->assertSession()
      ->pageTextContains('Current threats field is required.');
    $this->assertSession()
      ->pageTextContains('Overall current threats rating field is required.');
    $this->assertSession()
      ->pageTextContains('Overall Assessment of current Threats field is required.');
    $this->assertSession()
      ->pageTextContains('Overall potential threats rating field is required.');
    $this->assertSession()
      ->pageTextContains('Overall Assessment of Potential Threats field is required.');
    $this->assertSession()
      ->pageTextContains('Overall assessment of threats rating field is required.');
    $this->assertSession()
      ->pageTextContains('Overall Assessment of Threats field is required.');
    $this->assertSession()
      ->pageTextContains('Identifying and describing values field is required.');
    $this->assertSession()->pageTextContains('Assessment field is required.');
    $this->assertSession()
      ->pageTextContains('Assessment of the current state and trend of World Heritage values field is required.');
    $this->assertSession()->pageTextContains('Trend field is required.');

    $this->assertSession()
      ->pageTextContains('Summary of the values - Assessment field is required.');
    $this->assertSession()
      ->pageTextContains('Summary of the values - Justification of assessment field is required.');
    $this->assertSession()
      ->pageTextContains('Summary of the values - Trend field is required.');
    $this->assertSession()
      ->pageTextContains('Summary of benefits field is required.');

    $assessment->get('field_as_values_bio')->setValue(NULL);
    $assessment->get('field_as_benefits')->setValue(NULL);
    $assessment->save();
    $this->drupalGet($stateChangeForm);
    // These fields are required only when field_as_values_bio or field_as_benefits_summary are filled.
    $this->assertSession()
      ->pageTextNotContains('Summary of the values - Assessment field is required.');
    $this->assertSession()
      ->pageTextNotContains('Summary of the values - Justification of assessment field is required.');
    $this->assertSession()
      ->pageTextNotContains('Summary of the values - Trend field is required.');
    $this->assertSession()
      ->pageTextNotContains('Summary of benefits field is required.');

    // Create valid assessment.
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    foreach (self::REQUIRED_PARAGRAPH_FIELDS as $paragraphField => $fields) {
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      $paragraph = $assessment->get($paragraphField)->entity;
      foreach ($fields as $field) {
        $paragraph->get($field)->setValue(NULL);
      }
      $paragraph->save();
    }

    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextContains('Specific benefits, Summary fields are required for all rows in Understanding benefits table.');
    $this->assertSession()
      ->pageTextContains('Description, Organisation fields are required for all rows in Compilation of active conservation projects table.');
    $this->assertSession()
      ->pageTextContains('Justification of assessment, Assessment, Topic fields are required for all rows in Assessing Protection and Management table.');
    $this->assertSession()
      ->pageTextContains('Reference field is required for all rows in References table.');
    $this->assertSession()
      ->pageTextContains('Description, Value fields are required for all rows in Other important biodiversity values table.');
    $this->assertSession()
      ->pageTextContains('Description, WH Criteria, Value fields are required for all rows in Identifying and describing values table.');
    $this->assertSession()
      ->pageTextContains('State, Justification of assessment, Trend fields are required for all rows in Assessing values table.');
    $this->assertSession()
      ->pageTextContains('Justification, Category, Assessment, Specific threat affecting site fields are required for all rows in Current threats table.');
    $this->assertSession()
      ->pageTextContains('Justification, Category, Assessment, Specific threat affecting site fields are required for all rows in Potential threats table.');

    // Test level 2 categories.
    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    foreach (self::CATEGORY_FIELDS as $field => $categoryField) {
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      $paragraph = $assessment->get($field)->entity;
      $categories = array_column($paragraph->get($categoryField)
        ->getValue(), 'target_id');
      $parent = end($categories);
      $paragraph->get($categoryField)->setValue($parent);
      $paragraph->save();
      $categories = array_column($paragraph->get($categoryField)
        ->getValue(), 'target_id');
    }
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('Subcategories field is required');
    $this->assertSession()
      ->pageTextNotContains('Specific benefits field is required for all rows in Understanding benefits table.');

    foreach (self::CATEGORY_FIELDS as $field => $categoryField) {
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      $paragraph = $assessment->get($field)->entity;
      $category = $paragraph->get($categoryField)->entity;
      $parentCategory = $category->parent->target_id;
      $paragraph->get($categoryField)->setValue($parentCategory);
      $paragraph->save();
      $categories = array_column($paragraph->get($categoryField)
        ->getValue(), 'target_id');
    }
    $this->drupalGet($stateChangeForm);
    $this->assertSession()->pageTextContains('Subcategories field is required');
    $this->assertSession()
      ->pageTextContains('Specific benefits field is required for all rows in Understanding benefits table.');

    /** @var \Drupal\paragraphs\ParagraphInterface $threat */
    $threat = $assessment->field_as_threats_current->entity;
    $threat->get('field_as_threats_out')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('At least one option must be selected for Inside site/Outside site');

    $threat->get('field_as_threats_out')->setValue(TRUE);
    $threat->get('field_as_threats_in')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('At least one option must be selected for Inside site/Outside site');

    $threat->get('field_as_threats_out')->setValue(NULL);
    $threat->get('field_as_threats_in')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextContains('At least one option must be selected for Inside site/Outside site');

    $threat->get('field_as_threats_extent')->setValue(NULL);
    $threat->get('field_as_threats_in')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('Threat extent field is required for');

    $threat->get('field_as_threats_extent')->setValue(NULL);
    $threat->get('field_as_threats_in')->setValue(TRUE);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextContains('Threat extent field is required for');

    $threat->get('field_as_threats_values_wh')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('Affected values field is required');

    $threat->get('field_as_threats_values_wh')->setValue([
      'target_id' => $assessment->get('field_as_values_wh')->target_id,
      'target_revision_id' => $assessment->get('field_as_values_wh')->target_revision_id,
    ]);
    $threat->get('field_as_threats_values_bio')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextNotContains('Affected values field is required');

    $threat->get('field_as_threats_values_wh')->setValue(NULL);
    $threat->get('field_as_threats_values_bio')->setValue(NULL);
    $threat->save();
    $this->drupalGet($stateChangeForm);
    $this->assertSession()
      ->pageTextContains('Affected values field is required');

    $assessment = $this->createMockAssessmentNode(AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    $stateChangeForm = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $assessment->id()]);
    /** @var \Drupal\paragraphs\ParagraphInterface $threat */
    $threat = $assessment->field_as_threats_current->entity;
    $categories = array_column($threat->get('field_as_threats_categories')
      ->getValue(), 'target_id');
    $parentCategory = reset($categories);

    foreach (self::REQUIRED_DEPENDENT_FIELDS as $field => $categories) {
      $threat->get($field)->setValue(NULL);
      $threat->save();
      $this->drupalGet($stateChangeForm);
      $this->assertSession()->pageTextNotContains($categories['message']);
      foreach ($categories['subcategories'] as $category) {
        $term = Term::load($category);
        if (!$term instanceof TermInterface) {
          $term = TestSupport::createSampleEntity('taxonomy_term', 'assessment_threat', [
            'name' => $category,
            'tid' => $category,
            'parent' => $parentCategory,
          ]);
        }
        $threat->get('field_as_threats_categories')->setValue($term);
        $threat->save();
        $this->drupalGet($stateChangeForm);
        $this->assertSession()->pageTextContains($categories['message']);
      }
    }
  }


  public function testFieldsAccess() {
    $fieldsAccess = [
      TestSupport::ASSESSOR1 => [
        AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
      ],
      TestSupport::REVIEWER1 => [
        AssessmentWorkflow::STATUS_UNDER_REVIEW => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],

      ],
      TestSupport::REFERENCES_REVIEWER1 => [
        AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
      ],
      TestSupport::COORDINATOR1 => [
        AssessmentWorkflow::STATUS_NEW => [
          'field_assessor' => FALSE,
          'field_coordinator' => TRUE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_UNDER_EVALUATION => [
          'field_assessor' => TRUE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => [
          'field_assessor' => TRUE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_READY_FOR_REVIEW => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => TRUE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_UNDER_REVIEW => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => TRUE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_FINISHED_REVIEWING => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_UNDER_COMPARISON => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => TRUE,
        ],
        AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
        AssessmentWorkflow::STATUS_FINAL_CHANGES => [
          'field_assessor' => FALSE,
          'field_coordinator' => FALSE,
          'field_reviewers[]' => FALSE,
          'field_references_reviewer' => FALSE,
        ],
      ],
    ];
    $fieldsAccess[TestSupport::IUCN_MANAGER] = $fieldsAccess[TestSupport::COORDINATOR1];

    foreach (WorkflowTestBase::TRANSITION_LABELS as $state => $label) {
      $assessment = $this->createMockAssessmentNode($state);

      foreach ($fieldsAccess as $user => $access) {
        if (empty($access[$state])) {
          continue;
        }

        $this->userLogIn($user);
        $this->drupalGet(Url::fromRoute(
          'iucn_assessment.node.state_change',
          ['node' => $assessment->id()]
        ));

        foreach ($access[$state] as $fieldName => $visibility) {
          /** @var \Behat\Mink\Element\NodeElement $htmlField */
          $htmlField = $this->getSession()->getPage()->findField($fieldName);
          $this->assertTrue($htmlField instanceof NodeElement);

          if (empty($htmlField)) {
            // Do not crash the test if the field was not found, the assertTrue
            // above is enough.
            continue;
          }

          $disabledAttribute = $htmlField->getAttribute('disabled');
          $text = $value = 'disabled';
          if ($visibility === TRUE) {
            $text = 'active';
            $value = NULL;
          }
          $message = "Field {$fieldName} is {$text} for user {$user} when assessment state is {$state}";
          $this->assertEquals($disabledAttribute, $value, $message);
        }
      }
    }
  }
}
