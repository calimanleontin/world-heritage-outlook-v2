<?php

namespace Drupal\Tests\iucn_assessment\Functional\Form;

use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\Tests\iucn_assessment\Functional\TestSupport;
use Drupal\Tests\iucn_assessment\Functional\Workflow\WorkflowTestBase;

class UserFieldsAccessTest extends WorkflowTestBase {

  public function testIucnManagerAccess() {
    //Checks what fields are disabled for role iucn_manager
    $states = [
      AssessmentWorkflow::STATUS_UNDER_EVALUATION,
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW,
      AssessmentWorkflow::STATUS_UNDER_REVIEW,
      AssessmentWorkflow::STATUS_FINISHED_REVIEWING,
      AssessmentWorkflow::STATUS_UNDER_COMPARISON,
      AssessmentWorkflow::STATUS_REVIEWING_REFERENCES,
      AssessmentWorkflow::STATUS_FINAL_CHANGES,
    ];

    $managerFieldMapping = [
      AssessmentWorkflow::STATUS_NEW => [
        'field_assessor' => 'disabled',
        'field_coordinator' => NULL,
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_UNDER_EVALUATION => [
        'field_assessor' => NULL,
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_UNDER_ASSESSMENT => [
        'field_assessor' => NULL,
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_READY_FOR_REVIEW => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => NULL,
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_UNDER_REVIEW => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => NULL,
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_FINISHED_REVIEWING => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_UNDER_COMPARISON => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => NULL,
      ],
      AssessmentWorkflow::STATUS_REVIEWING_REFERENCES => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
      AssessmentWorkflow::STATUS_FINAL_CHANGES => [
        'field_assessor' => 'disabled',
        'field_coordinator' => 'disabled',
        'field_reviewers[]' => 'disabled',
        'field_references_reviewer' => 'disabled',
      ],
    ];
    $this->checkRoleAccess(
      TestSupport::IUCN_MANAGER,
      $states,
      $managerFieldMapping
    );
  }

  private function checkRoleAccess($role, $states, $fieldMapping) {
    $this->userLogIn(TestSupport::ADMINISTRATOR);
    $stateChangeUrl = Url::fromRoute(
      'iucn_assessment.node.state_change',
      ['node' => $this->assessment->id()]
    );

    $nextState = current($states);
    while (TRUE) {
      $currentState = $this->assessment->get('field_state')->value;
      if (!empty($fieldMapping[$currentState])) {
        $this->checkRoleAccessOnFields(
          $role,
          $fieldMapping[$currentState],
          $stateChangeUrl,
          $currentState
        );
      }

      if (empty($nextState)) {
        break;
      }

      $label = $this->getAdminTransitionLabel($nextState);

      $this->drupalPostForm($stateChangeUrl, [], $label);
      drupal_flush_all_caches();
      $this->assessment = Node::load($this->assessment->id());
      $nextState = next($states);
    }
  }

  private function checkRoleAccessOnFields(
    $role,
    $fields,
    $stateChangeUrl,
    $currentState
  ) {
    $this->userLogIn($role);
    $this->drupalGet($stateChangeUrl);

    foreach ($fields as $field => $disabledValue) {
      $this->assertEquals(
        $this->getSession()
          ->getPage()
          ->findField($field)
          ->getAttribute('disabled'),
        $disabledValue,
        sprintf(
          "Field %s \"disabled\" property value is %s, for role %s when assessment state is %s",
          $field,
          $disabledValue ?: "null",
          $role,
          $currentState
        )
      );
    }
  }

}
