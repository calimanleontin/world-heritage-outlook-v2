<?php

namespace Drupal\iucn_assessment\Tests\Workflow;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Tests\IucnAssessmentTestBase;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;

class WorkflowTestBase extends IucnAssessmentTestBase {

  const TRANSITION_LABELS = [
    AssessmentWorkflow::STATUS_NEW => 'Save',
    AssessmentWorkflow::STATUS_UNDER_EVALUATION => 'Initiate assessment',
    AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => 'Send to assessor',
    AssessmentWorkflow::STATUS_READY_FOR_REVIEW => 'Finish assessment',
    AssessmentWorkflow::STATUS_UNDER_REVIEW => 'Send assessment to reviewers',
    AssessmentWorkflow::STATUS_FINISHED_REVIEWING => 'Finish reviewing',
    AssessmentWorkflow::STATUS_UNDER_COMPARISON => 'Start comparing reviews',
    AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => 'Review references',
    AssessmentWorkflow::STATUS_APPROVED => 'Approve',
    AssessmentWorkflow::STATUS_PUBLISHED => 'Publish',
    AssessmentWorkflow::STATUS_DRAFT => 'Start working on a draft',
  ];

  public function checkUserAccess(Url $url, $user, $expectedResponseCode) {
    $this->userLogIn($user);
    $this->drupalGet($url);
    $this->assertResponse($expectedResponseCode, "User {$user} tries to access {$url->toString()} and the HTTP response code should be {$expectedResponseCode}.");
  }

}
