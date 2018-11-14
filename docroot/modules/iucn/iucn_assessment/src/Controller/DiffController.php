<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\iucn_assessment\Form\NodeSiteAssessmentForm;
use Drupal\iucn_who_diff\Controller\DiffModalFormController;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Revision comparison service that prepares a diff of a pair of revisions.
 */
class DiffController extends ControllerBase {

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\diff\DiffEntityComparison */
  protected $entityComparison;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    // Can't add it to arguments list in services.yml because of the following error:
    // The service "iucn_assessment.diff_controller" has a dependency on a non-existent service "diff.entity_comparison".
    $this->entityComparison = \Drupal::service('diff.entity_comparison');
  }

  public function compareRevisions($vid1, $vid2) {
    $revision1 = $this->nodeStorage->loadRevision($vid1);
    $revision2 = $this->nodeStorage->loadRevision($vid2);

    if (!$revision1 instanceof NodeInterface || !$revision2 instanceof NodeInterface) {
      throw new \InvalidArgumentException('Invalid revisions ids.');
    }
    if ($revision1->id() != $revision2->id()) {
      throw new \InvalidArgumentException('Can only compare 2 revisions of same node.');
    }

    $fields = $this->entityComparison->compareRevisions($revision1, $revision2);

    $diff = [];
    foreach ($fields as $key => $field) {
      if (preg_match('/(\d+)\:(.+)\.(.+)/', $key, $matches)) {
        $this->entityComparison->processStateLine($field);
        $field_diff_rows = $this->entityComparison->getRows(
          $field['#data']['#left'],
          $field['#data']['#right']
        );
        if (!empty($field_diff_rows)) {
          $entityId = $matches[1];
          $entityType = $matches[2];
          $fieldName = $matches[3];
          if (empty($diff[$entityId])) {
            $diff[$entityId] = [
              'entity_id' => $entityId,
              'entity_type' => $entityType,
              'diff' => [],
            ];
          }
          $diff[$entityId]['diff'][$fieldName][] = $field_diff_rows;
        }
      }
      else {
        $this->getLogger('iucn_diff')->error('Invalid field diff key.');
      }
    }
    return $diff;
  }

  public function diffForm($parent_entity_type, $parent_entity_bundle, $parent_entity_revision, $field, $field_wrapper_id, $delta, $paragraph, $paragraph_revision, $js = 'nojs') {
    $response = new AjaxResponse();

    // Get the rendered field from the entity form.
    $form = \Drupal::service('entity.form_builder')->getForm($parent_entity_revision, 'default')[$field];
    // Remove unnecessary data from the table.
    NodeSiteAssessmentForm::hideParagraphsActionsFromWidget($form['widget'], FALSE);
    unset($form['widget']['#title']);
    unset($form['widget']['#description']);

    $form['widget']['#hide_draggable'] = TRUE;
    $paragraph_key = 0;
    foreach ($form['widget'] as $key => &$item) {
      if (!is_int($key)) {
        continue;
      }
      if ($item['#paragraph_id'] != $paragraph->id()) {
        unset($form['widget'][$key]);
      }
      else {
        $paragraph_key = $key;
      }
    }

    // Add the author table cell.
    $author = $parent_entity_revision->field_coordinator->entity->getDisplayName();
    $author_header = DiffModalFormController::getTableCellMarkup(t('Author'), 'author');
    $author_container = DiffModalFormController::getTableCellMarkup($author, 'author');
    $form['widget'][$paragraph_key]['top']['summary'] = ['author' => $author_container] + $form['widget'][$paragraph_key]['top']['summary'];
    $form['widget']['header']['data'] = ['author' => $author_header] + $form['widget']['header']['data'];

    $settings = json_decode($parent_entity_revision->field_settings->value, TRUE);
    $diff = $settings['diff'];
    foreach ($settings['diff'] as $assessment_vid => $diff) {
      // For each revision that changed this paragraph.
      if (empty($diff[$paragraph_revision->id()])) {
        continue;
      }
      /** @var NodeInterface $assessment_revision */
      $assessment_revision = \Drupal::service('iucn_assessment.workflow')->getAssessmentRevision($assessment_vid);
      $author = User::load($assessment_revision->getRevisionUserId())->getDisplayName();

      // Copy the initial row.
      $row = $form['widget'][$paragraph_key];
      $diff_fields = array_keys($diff[$paragraph_revision->id()]['diff']);

      // Alter fields that have differences.
      foreach ($diff_fields as $diff_field) {
        if (empty($row['top']['summary'][$diff_field]['data'])) {
          continue;
        }
        $diffs = reset(reset($diff[$paragraph_revision->id()]['diff'][$diff_field]));
        $diff_rows = [];
        for ($i = 0; $i < count($diffs); $i += 2) {
          $diff_rows[] = [$diffs[$i], $diffs[$i + 1]];
        }
        $row['top']['summary'][$diff_field]['data'] = [
          '#type' => 'table',
          '#rows' => $diff_rows,
          '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        ];
      }
      $row['top']['summary']['author']['data']['#markup'] = $author;
      $form['widget'][] = $row;
    }
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['widget']['#is_diff_form'] = TRUE;

    $assessment_edit_form = \Drupal::service('entity.form_builder')->getForm($paragraph_revision, 'geysir_modal_edit', []);
    $form['edit'] = $assessment_edit_form;

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('See differences', $form, ['width' => '80%']));
    return $response;
  }

}