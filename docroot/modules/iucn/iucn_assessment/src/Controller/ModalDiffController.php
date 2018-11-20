<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\geysir\Ajax\GeysirOpenModalDialogCommand;
use Drupal\iucn_assessment\Form\NodeSiteAssessmentForm;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\iucn_assessment\Plugin\Field\FieldWidget\RowParagraphsWidget;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the diff modal.
 */
class ModalDiffController extends ControllerBase {

  /**
   * @var AssessmentWorkflow
   */
  public $assessmentWorkflow;

  /**
   * @var EntityFormBuilder
   */
  public $formBuilder;

  public function __construct(EntityFormBuilder $formBuilderService, AssessmentWorkflow $assessmentWorkflowService) {
    $this->formBuilder = $formBuilderService;
    $this->assessmentWorkflow = $assessmentWorkflowService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('iucn_assessment.workflow')
    );
  }

  public function paragraphDiffForm(NodeInterface $node, $node_revision, $field, $field_wrapper_id, $paragraph_revision) {
    $response = new AjaxResponse();

    $parent_entity_revision = $this->assessmentWorkflow->getAssessmentRevision($node_revision);

    // Get the rendered field from the entity form.
    $form = $this->formBuilder->getForm($parent_entity_revision, 'default')[$field];
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
      if ($item['#paragraph_id'] != $paragraph_revision->id()) {
        unset($form['widget'][$key]);
      }
      else {
        $paragraph_key = $key;
      }
    }

    // Add the author table cell.
    $author = $parent_entity_revision->field_coordinator->entity->getDisplayName();
    $author_header = $this->getTableCellMarkup(t('Author'), 'author');
    $author_container = $this->getTableCellMarkup($author, 'author');
    $form['widget'][$paragraph_key]['top']['summary'] = ['author' => $author_container] + $form['widget'][$paragraph_key]['top']['summary'];
    $form['widget']['header']['data'] = ['author' => $author_header] + $form['widget']['header']['data'];

    $settings = json_decode($parent_entity_revision->field_settings->value, TRUE);
    $diff = $settings['diff'];
    foreach ($settings['diff'] as $assessment_vid => $diff) {
      // For each revision that changed this paragraph.
      if (empty($diff[$paragraph_revision->id()] || $diff[$paragraph_revision->id()]['entity_type'] != 'paragraph')) {
        continue;
      }
      /** @var NodeInterface $assessment_revision */
      $assessment_revision = $this->assessmentWorkflow->getAssessmentRevision($assessment_vid);

      $author = User::load($assessment_revision->getRevisionUserId())->getDisplayName();

      // Copy the initial row.
      $row = $form['widget'][$paragraph_key];
      $diff_fields = array_keys($diff[$paragraph_revision->id()]['diff']);

      // If the row is actually deleted, only apply a different class.
      $deleted = FALSE;
      if (!in_array($paragraph_revision->id(), array_column($assessment_revision->get($field)->getValue(), 'target_id'))) {
        $row['top']['#attributes']['class'][] = 'paragraph-deleted-row';
        $deleted = TRUE;
      }

      $grouped_fields = RowParagraphsWidget::getGroupedFields();

      // Alter fields that have differences.
      foreach ($diff_fields as $diff_field) {
        $grouped_with = !empty($grouped_fields[$diff_field]) ? $grouped_fields[$diff_field]['grouped_with'] : $diff_field;
        if (empty($row['top']['summary'][$diff_field]['data']) && empty($row['top']['summary'][$grouped_with]['data'])) {
          continue;
        }
        if ($deleted) {
          $row['top']['summary'][$grouped_with]['data']['#markup'] = $this->t('Deleted');
          continue;
        }
        $diffs = $diff[$paragraph_revision->id()]['diff'][$diff_field];
        $diff_rows = [];
        foreach ($diffs as $diff_group) {
          for ($i = 0; $i < count($diff_group); $i += 2) {
            $diff_rows[] = [$diff_group[$i], $diff_group[$i + 1]];
          }
        }

        if (!empty($row['top']['summary'][$grouped_with]['data'][$diff_field])) {
          $row['top']['summary'][$grouped_with]['data'][$diff_field] = [];
        }
        $row['top']['summary'][$grouped_with]['data'][$diff_field] = [
          '#type' => 'table',
          '#rows' => $diff_rows,
          '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        ];
        if (!empty($row['top']['summary'][$grouped_with]['data']['#markup'])) {
          unset($row['top']['summary'][$grouped_with]['data']['#markup']);
        }
        if (!empty($prefix = RowParagraphsWidget::getSummaryPrefix($diff_field))) {
          $row['top']['summary'][$grouped_with]['data'][$diff_field]['#prefix'] = $prefix;
        }
      }

      $row['top']['summary']['author']['data']['#markup'] = $author;
      $form['widget'][] = $row;
    }
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['widget']['#is_diff_form'] = TRUE;
    $form['widget']['edit'] = $form['widget'][$paragraph_key];

    $form['widget']['edit']['top']['summary']['author']['data']['#markup'] = '<b>' . t('Final version') . '</b>';
    $form['widget']['edit']['top']['#attributes']['class'][] = 'paragraph-diff-final';

    $assessment_edit_form = $this->formBuilder->getForm($paragraph_revision, 'iucn_modal_paragraph_edit', []);
    foreach (RowParagraphsWidget::getFieldComponents($paragraph_revision) as $field => $data) {
      $grouped_with = !empty($grouped_fields[$field]) ? $grouped_fields[$field]['grouped_with'] : $field;
      if (in_array($field, array_keys($assessment_edit_form))) {
        if (!empty($assessment_edit_form[$field]['widget']['#title_display'])) {
          $assessment_edit_form[$field]['widget']['#title_display'] = 'invisible';
        }
        if (!empty($assessment_edit_form[$field]['widget'][0]['value']['#title_display'])) {
          $assessment_edit_form[$field]['widget'][0]['value']['#title_display'] = 'invisible';
        }
        unset($form['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
        $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field] = $assessment_edit_form[$field];
        if ($field != $grouped_with) {
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field]['#prefix'] =
            '<b>' . RowParagraphsWidget::getSummaryPrefix($field) . '</b>';
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$grouped_with]['#prefix'] =
            '<b>' . RowParagraphsWidget::getSummaryPrefix($grouped_with) . '</b>';
        }
        unset($assessment_edit_form[$field]);
      }
    }

    $assessment_edit_form['diff'] = $form;
    $assessment_edit_form['diff']['#weight'] = 0;
    unset($assessment_edit_form['#fieldgroups']);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand($this->t('See differences'), $assessment_edit_form, ['width' => '80%']));
    return $response;
  }

  public function getTableCellMarkup($markup, $class, $span = 1) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'paragraph-summary-component',
          "paragraph-summary-component-$class",
          "paragraph-summary-component-span-$span",
        ],
      ],
      'data' => ['#markup' => $markup],
    ];
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

}
