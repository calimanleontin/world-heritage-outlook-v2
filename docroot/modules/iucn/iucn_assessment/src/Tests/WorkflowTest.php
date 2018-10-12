<?php

namespace Drupal\iucn_assessment\Tests;

class WorkflowTest extends IucnAssessmentTestBase {

  public function testAssessmentWorkflow() {
    $assessment = TestSupport::getNodeByTitle(TestSupport::ASSESSMENT1);
    $this->assertNotNull($assessment);
  }

}
