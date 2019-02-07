<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Ajax\OpenModalDialogCommand;

class IucnModalController extends ControllerBase {

  /**
   * Create a modal dialog to add the first paragraph.
   */
  public function addParagraph($node_revision, $field, $field_wrapper_id, $bundle) {
    $response = new AjaxResponse();
    $paragraph_title = $this->getParagraphTitle($field);

    $new_paragraph = Paragraph::create(['type' => $bundle]);
    $form = $this->entityFormBuilder()->getForm($new_paragraph, 'iucn_modal_paragraph_add', []);

    $response->addCommand(new OpenModalDialogCommand($this->t('Add @paragraph_title', ['@paragraph_title' => $paragraph_title]), $form, ['width' => '60%', 'classes' => ['ui-dialog' => 'add-paragraph-form-modal'] ]));
    return $response;
  }

  /**
   * Create a modal dialog to edit a single paragraph.
   */
  public function editParagraph($node, $node_revision, $field, $field_wrapper_id, $paragraph_revision) {
    $response = new AjaxResponse();
    $form = $this->entityFormBuilder()->getForm($paragraph_revision, 'iucn_modal_paragraph_edit', []);
    $paragraph_title = $this->getParagraphTitle($field);
    $map = [
      'field_as_threats_current' => 'current threat',
      'field_as_threats_potential' => 'potential threat',
      'field_as_protection' => 'protection and management topic',
      'field_as_values_wh' => 'state and trend of World Heritage value',
    ];
    if (isset($map[$field])) {
      $paragraph_title = $map[$field];
    }
    $response->addCommand(new OpenModalDialogCommand($this->t('Edit @paragraph_title', ['@paragraph_title' => $paragraph_title]), $form, ['width' => '60%','classes' => ['ui-dialog' => 'edit-paragraph-form-modal'] ]));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTitle($field) {
    $target_paragraph = FieldConfig::loadByName('node', 'site_assessment', $field)
      ->getSetting('handler_settings')['target_bundles'];
    $target_paragraph = reset($target_paragraph);
    $type = ParagraphsType::load($target_paragraph);

    return $type->label();
  }

  /**
   * Create a modal dialog to delete a single paragraph.
   */
  public function deleteParagraph($node, $node_revision, $field, $field_wrapper_id, $paragraph_revision) {
    $response = new AjaxResponse();
    $form = $this->entityFormBuilder()->getForm($paragraph_revision, 'iucn_modal_paragraph_delete', []);
    $paragraph_title = $this->getParagraphTitle($field);
    $response->addCommand(new OpenModalDialogCommand($this->t('Edit @paragraph_title', ['@paragraph_title' => $paragraph_title]), $form, ['width' => '60%']));

    return $response;
  }

}
