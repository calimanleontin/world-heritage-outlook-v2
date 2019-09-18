<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\iucn_assessment\Form\NodeSiteAssessmentForm;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
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
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $ids = $this->getRevisionIds($node, $node_storage);
    for ($i = 0; $i < count($ids); $i++) {
      if (empty($build['node_revisions_table']['#rows'][$i][1]['data'])) {
        continue;
      }
      array_unshift($build['node_revisions_table']['#rows'][$i][1]['data']['#links'], [
        'title' => $this->t('View revision'),
        'url' => Url::fromRoute('iucn_assessment.node.revision_view', ['node' => $node->id(), 'node_revision' => $ids[$i]])
      ]);
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

}
