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

  public function paragraphDiffForm(NodeInterface $node, NodeInterface $node_revision, $field, $field_wrapper_id, ParagraphInterface $paragraph_revision) {
    $response = new AjaxResponse();
    $form = $this->entityFormBuilder()->getForm($paragraph_revision, 'iucn_modal_paragraph_diff', []);
    $response->addCommand(new OpenModalDialogCommand($this->t('See differences'), $form, ['width' => '100%', 'height' => '100%', 'classes' => ['ui-dialog' => 'paragraph-diff-form-modal'] ]));

    return $response;
  }

  public function fieldDiffForm(NodeInterface $node, NodeInterface $node_revision, $field, $field_wrapper_id) {
    $response = new AjaxResponse();
    $form = $this->entityFormBuilder()->getForm($node_revision, 'iucn_modal_field_diff');
    $response->addCommand(new OpenModalDialogCommand($this->t('See differences'), $form, ['width' => '100%', 'height' => '100%', 'classes' => ['ui-dialog' => 'field-diff-form-modal'] ]));
    return $response;
  }

}
