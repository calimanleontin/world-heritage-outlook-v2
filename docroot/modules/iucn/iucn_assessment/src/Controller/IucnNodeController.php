<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Form\NodeSiteAssessmentForm;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IucnNodeController extends NodeController {

  /**
   * Alter the revisions page to add edit buttons for under_review revisions.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   *
   * @param \Drupal\node\NodeInterface $node
   * @param null $node_revision
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function stateChangeForm(NodeInterface $node, NodeInterface $node_revision = NULL) {
    if ($node->bundle() != 'site_assessment') {
      throw new NotFoundHttpException();
    }
    if (!empty($node_revision)) {
      $node = $node_revision;
    }
    $edit_form = \Drupal::entityTypeManager()->getFormObject('node', 'state_change')->setEntity($node);
    $build = \Drupal::formBuilder()->getForm($edit_form);
    $build['current_state'] = NodeSiteAssessmentForm::getCurrentStateMarkup($node);
    return $build;
  }

  public function revertParagraph(NodeInterface $node, $field, $field_wrapper_id, ParagraphInterface $paragraph) {
    $node->get($field)->appendItem(['entity' => $paragraph]);
    $settings = $node->field_settings->value;
    $changed_settings = FALSE;
    if (!empty($settings)) {
      $settings = json_decode($settings, TRUE);
      if (!empty($settings['diff'])) {
        foreach (array_keys($settings['diff']) as $vid) {
          if (!empty($settings['diff'][$vid][$paragraph->id()])) {
            unset($settings['diff'][$vid][$paragraph->id()]);
            $changed_settings = TRUE;
          }
        }
      }
    }
    if ($changed_settings) {
      $node->field_settings->value = json_encode($settings);
    }
    $node->save();
    $response = new AjaxResponse();
    $response->addCommand(
      new HtmlCommand(
        $field_wrapper_id,
        \Drupal::service('entity.form_builder')->getForm($node, 'default')[$field]
      )
    );
    return $response;
  }

}
