<?php


namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\Tests\iucn_assessment\Functional\IucnAssessmentTestBase;

class EditRevisionFormTest extends IucnAssessmentTestBase {

  public function testRevisionReadonlyAccess() {
    $assessment = $this->createMockAssessmentNode(
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      [],
      TRUE
    );

    $this->drupalGet($assessment->toUrl('version-history'));
    $this->assertSession()->pageTextContains(t('View revision'));

    /** @var AssessmentWorkflow $assessmentWorkflow */
    $assessmentWorkflow = \Drupal::service('iucn_assessment.workflow');

    $underAssessmentRevision = $assessmentWorkflow->getRevisionByState(
      $assessment,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT
    );

    $url = Url::fromRoute(
      'iucn_assessment.node.revision_view',
      [
        'node' => $assessment->id(),
        'node_revision' => $underAssessmentRevision->id(),
      ]
    );

    $this->drupalGet($url);
    $this->checkReadOnlyAccess();

    $tabs = [
      'assessing-values',
      'threats',
      'protection-management',
      'benefits',
      'projects',
      'references',
    ];

    foreach ($tabs as $tab) {
      $url->setOptions(['query' => ['tab' => $tab]]);
      $this->drupalGet($url);
      $this->checkReadOnlyAccess();
    }
  }

}
