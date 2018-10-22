<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IucnNodeController extends NodeController {

  /**
   * Alter the revisions page to add edit buttons for under_review revisions.
   */
  public function revisionOverview(NodeInterface $node) {
    $build = parent::revisionOverview($node);
    foreach ($build['node_revisions_table']['#rows'] as &$row) {
      foreach ($row as &$column) {
        if (!empty($column['data']) && !empty($column['data']['#type']) && $column['data']['#type'] == 'operations') {
          /** @var \Drupal\Core\Url $delete_route */
          $delete_route = $column['data']['#links']['delete']['url'];
          $vid = $delete_route->getRouteParameters()['node_revision'];
          $revision = $node_revision = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadRevision($vid);
          if ($revision->field_state->value != AssessmentWorkflow::STATUS_UNDER_REVIEW) {
            continue;
          }
          $edit_route = Url::fromRoute('node.revision_edit', ['node' => $node->id(), 'node_revision' => $vid]);
          $column['data']['#links']['edit'] = [
            'title' => $this->t('Edit'),
            'url' => $edit_route,
          ];
        }
      }
    }

    return $build;
  }

  /**
   * Prepare the iucn_assessment.node.state_change route.
   */
  public function stateChangeForm(NodeInterface $node, $node_revision = NULL) {
    if ($node->bundle() != 'site_assessment') {
      throw new NotFoundHttpException();
    }
    if (!empty($node_revision)) {
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($node_revision);
      if (empty($node)) {
        throw new NotFoundHttpException();
      }
    }
    $edit_form = \Drupal::entityTypeManager()->getFormObject('node', 'state_change')->setEntity($node);
    $build = \Drupal::formBuilder()->getForm($edit_form);
    $current_state = $node->field_state->value;
    if (!empty($current_state)) {
      $state_entity = WorkflowState::load($current_state);
    }
    else {
      $state_entity = NULL;
    }
    $state_label = !empty($state_entity) ? $state_entity->label() : 'Creation';
    $build['current_state'] = [
      '#weight' => 9999,
      '#type' => 'markup',
      '#markup' => $this->t('Current state: <b>@state</b>', ['@state' => $state_label]),
    ];
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    if (!$workflow_service->isAssessmentEditable($node)) {
      $message = $this->t('The assessment is not editable in this state.');
      if ($current_state == $workflow_service::STATUS_UNDER_REVIEW) {
        $unfinished_reviews = $workflow_service->getUnfinishedReviewerRevisions($node);
        if (!empty($unfinished_reviews)) {
          $reviewers = [];
          /** @var \Drupal\node\Entity\Node $unfinished_review */
          foreach ($unfinished_reviews as $unfinished_review) {
            $uid = $unfinished_review->getRevisionUserId();
            $user = User::load($uid)->getUsername();
            $reviewers[] = $user;
          }
          $message .= ' ' . $this->t('Please wait for the following reviewers to finish their reviews:') . ' ';
          $message .= implode(', ', $reviewers);
        }
      }
      elseif ($current_state == $workflow_service::STATUS_PUBLISHED) {
        $message .= ' ' . $this->t('Please create a draft first.');
      }
      elseif ($current_state == $workflow_service::STATUS_UNDER_ASSESSMENT) {
        $message .= ' ' . $this->t('Please wait for the assessment to be finished.');
      }
      \Drupal::messenger()->addWarning($message);
    }
    return $build;
  }

}
