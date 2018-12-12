<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Controller for the diff modal.
 */
class ModalDiffController extends ControllerBase {

  public function paragraphDiffForm(NodeInterface $node, $node_revision, $field, $field_wrapper_id, ParagraphInterface $paragraph_revision) {
    $response = new AjaxResponse();
    $form = $this->entityFormBuilder()->getForm($paragraph_revision, 'iucn_modal_paragraph_diff', []);
    $response->addCommand(new OpenModalDialogCommand($this->t('See differences'), $form, ['width' => '90%']));

    return $response;
  }

  public function fieldDiffForm(NodeInterface $node, $node_revision, $field, $field_wrapper_id) {
    $response = new AjaxResponse();
    $node_revision = $this->entityTypeManager()
      ->getStorage('node')
      ->loadRevision($node_revision);
    $form = $this->entityFormBuilder()->getForm($node_revision, 'iucn_modal_field_diff');
    $response->addCommand(new OpenModalDialogCommand($this->t('See differences'), $form, ['width' => '80%']));
    return $response;
  }

  public static function getDiffMarkup($diff) {
    $diff_rows = [];
    foreach ($diff as $diff_group) {
      for ($i = 0; $i < count($diff_group); $i += 2) {
        if (!empty($diff_group[$i + 1]['data']['#markup']) && !empty($diff_group[$i + 3]['data']['#markup'])
          && $diff_group[$i + 1]['data']['#markup'] == $diff_group[$i + 3]['data']['#markup']) {
          continue;
        }
        $diff_rows[] = [$diff_group[$i], $diff_group[$i + 1]];
      }
    }
    return $diff_rows;
  }

}
