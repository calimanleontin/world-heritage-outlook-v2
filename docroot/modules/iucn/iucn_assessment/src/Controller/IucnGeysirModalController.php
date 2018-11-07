<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\geysir\Ajax\GeysirOpenModalDialogCommand;
use Drupal\geysir\Controller\GeysirModalController;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller for all modal dialogs.
 */
class IucnGeysirModalController extends GeysirModalController {

  /**
   * Create a modal dialog to edit a single paragraph.
   */
  public function edit($parent_entity_type, $parent_entity_bundle, $parent_entity_revision, $field, $field_wrapper_id, $delta, $paragraph, $paragraph_revision, $js = 'nojs') {
    if ($js == 'ajax') {
      $response = new AjaxResponse();
      $form = $this->entityFormBuilder()->getForm($paragraph_revision, 'geysir_modal_edit', []);
      $paragraph_title = $this->getParagraphTitle($parent_entity_type, $parent_entity_bundle, $field);
      $response->addCommand(new GeysirOpenModalDialogCommand($this->t('Edit @paragraph_title', ['@paragraph_title' => $paragraph_title]), render($form)));

      return $response;
    }

    return $this->t('Javascript is required for this functionality to work properly.');
  }

}
