<?php

namespace Drupal\iucn_assessment\Tests;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

class WorkflowTest extends IucnAssessmentTestBase {

  public function testAssessmentWorkflow() {
    // Fresh assessment with state = NEW and no coordinator.
    $assessment = TestSupport::getNodeByTitle(TestSupport::ASSESSMENT1);

    $this->setAssessmentState($assessment, 'assessment_new');

    $this->assertEqual($assessment->field_state->value, 'assessment_new');
    $this->assertNull($assessment->field_coordinator->value);

    // Only coordinators and higher roles should be able to edit the assessment.
    $this->_testUserAccessOnAssessmentEdit(TestSupport::ADMINISTRATOR, $assessment, 200);
    $this->_testUserAccessOnAssessmentEdit(TestSupport::IUCN_MANAGER, $assessment, 200);
    $this->_testUserAccessOnAssessmentEdit(TestSupport::COORDINATOR1, $assessment, 200);

    $this->_testUserAccessOnAssessmentEdit(TestSupport::ASSESSOR1, $assessment, 403);
    $this->_testUserAccessOnAssessmentEdit(TestSupport::REVIEWER1, $assessment, 403);
  }

  protected function _testUserAccessOnAssessmentEdit($mail, NodeInterface $assessment, $assert_response_code, $vid = NULL) {
    if (empty($vid)) {
      $url = $assessment->toUrl('edit-form');
    }
    else {
      $url = Url::fromRoute('node.revision_edit', ['node' => $assessment->id(), 'node_revision' => $vid]);
    }
    $user = user_load_by_mail($mail);
    $user->pass_raw = 'password';
    $this->drupalLogin($user);
    $this->drupalGet($url);
    $this->assertResponse($assert_response_code);
  }

}
