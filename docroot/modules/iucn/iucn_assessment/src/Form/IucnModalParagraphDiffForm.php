<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Controller\ModalDiffController;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\iucn_assessment\Plugin\Field\FieldWidget\RowParagraphsWidget;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;

class IucnModalParagraphDiffForm extends IucnModalForm {

  use DiffModalTrait;

  /**
   * @var AssessmentWorkflow;
   */
  protected $assessmentWorkflow;

  /**
   * @var EntityFormBuilderInterface;
   */
  protected $formBuilder;

  public function buildForm(array $form, FormStateInterface $form_state) {
    $paragraph_form = parent::buildForm($form, $form_state);
    iucn_assessment_form_alter($paragraph_form, $form_state, self::getFormId());
    $paragraph_form['#processed'] = TRUE;
    $this->assessmentWorkflow = \Drupal::service('iucn_assessment.workflow');
    $this->formBuilder = \Drupal::service('entity.form_builder');

    $field = $this->getRouteMatch()->getParameter('field');
    /** @var ParagraphInterface $paragraph_revision */
    $paragraph_revision = $this->getRouteMatch()->getParameter('paragraph_revision');

    /** @var NodeInterface $parent_entity_revision */
    $parent_entity_revision = $this->getRouteMatch()->getParameter('node_revision');
    $settings = json_decode($parent_entity_revision->field_settings->value, TRUE);
    if (!empty($settings['diff']) && ($firstKey = key($settings['diff'])) && !empty($settings['diff'][$firstKey]['node'][$parent_entity_revision->id()]['initial_revision_id'])) {
      $form_revision = $this->assessmentWorkflow->getAssessmentRevision($settings['diff'][$firstKey]['node'][$parent_entity_revision->id()]['initial_revision_id']);
    }
    elseif ($parent_entity_revision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
      $form_revision = $this->assessmentWorkflow->getRevisionByState($parent_entity_revision, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    }
    else {
      $form_revision = $parent_entity_revision;
    }

    foreach ($form_revision->{$field}->getValue() as $value) {
      if (!empty($value['target_id']) && $value['target_id'] == $paragraph_revision->id()) {
        $form_revision->{$field}->setValue([0 => $value]);
        break;
      }
    }

    // Get the rendered field from the entity form.
    $form = $this->formBuilder->getForm($form_revision, 'default')[$field];
    // Remove unnecessary data from the table.
    NodeSiteAssessmentForm::hideParagraphsActionsFromWidget($form['widget'], FALSE);
    unset($form['widget']['#title']);
    unset($form['widget']['#description']);

    $form['widget']['#hide_draggable'] = TRUE;
    $paragraph_key = 0;

    // Add the author table cell.
    $this->addAuthorCell($form['widget']['header'], 'data', t('Author'), 'author', 2, -100);
    $this->addAuthorCell($form['widget'][$paragraph_key]['top'], 'summary', t('Initial version'), 'author', 2, -100);

    $initial_copy_value_buttons = [];
    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    foreach ($settings['diff'] as $assessment_vid => $diff) {
      // For each revision that changed this paragraph.
      if (empty($diff['paragraph'][$paragraph_revision->id()])) {
        continue;
      }
      $diff = $diff['paragraph'][$paragraph_revision->id()]['diff'];
      /** @var NodeInterface $assessment_revision */
      $assessment_revision = $this->assessmentWorkflow->getAssessmentRevision($assessment_vid);

      if ($parent_entity_revision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        $author = $parent_entity_revision->field_assessor->entity->getDisplayName();
      }
      else {
        $author = User::load($assessment_revision->getRevisionUserId())->getDisplayName();
      }

      // Copy the initial row.
      $row = $form['widget'][$paragraph_key];
      $diff_fields = array_keys($diff);

      // If the row is actually deleted, only apply a different class.
      $deleted = FALSE;
      if (!in_array($paragraph_revision->id(), array_column($assessment_revision->get($field)->getValue(), 'target_id'))) {
        $row['top']['#attributes']['class'][] = 'paragraph-deleted-row';
        $deleted = TRUE;
      }

      $grouped_fields = RowParagraphsWidget::getGroupedFields();
      $grouped_with_fields = [];
      foreach ($grouped_fields as $grouped_field => $group_settings) {
        $grouped_with = $group_settings['grouped_with'];

        $grouped_with_fields[] = $grouped_with;
        if ($paragraph_revision->hasField($grouped_field)) {
          $value1 = $paragraph_revision->get($grouped_field)->view(['settings' => ['link' => 0]]);
          $value1['#title'] = RowParagraphsWidget::getSummaryPrefix($grouped_field);
        }

        if ($paragraph_revision->hasField($grouped_with)) {
          $value2 = $paragraph_revision->get($grouped_with)->view(['settings' => ['link' => 0]]);
          $value2['#title'] = RowParagraphsWidget::getSummaryPrefix($grouped_with);
        }

        if (!empty($value1) && !empty($value2)) {
          $row['top']['summary'][$group_settings['grouped_with']]['data'][$grouped_with] = $value2;
          $row['top']['summary'][$group_settings['grouped_with']]['data'][$grouped_field] = $value1;
        }
      }

      // Alter fields that have differences.
      foreach ($diff_fields as $diff_field) {
        if (!empty($settings['diff'][$assessment_revision->getRevisionId()]['node'][$assessment_revision->id()]['initial_revision_id'])) {
          $initial_revision = $this->assessmentWorkflow->getAssessmentRevision($settings['diff'][$assessment_revision->getRevisionId()]['node'][$assessment_revision->id()]['initial_revision_id']);
          $data_value_0  = $initial_revision->get($field)->getValue()[$paragraph_key];
          $data_value= $assessment_revision->get($field)->getValue()[$paragraph_key];
        }
        elseif ($parent_entity_revision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
          $data_value_0 = $assessment_revision->get($field)->getValue()[$paragraph_key];
          $data_value = $parent_entity_revision->get($field)->getValue()[$paragraph_key];
        }
        else {
          $data_value_0 = $parent_entity_revision->get($field)->getValue()[$paragraph_key];
          $data_value = $assessment_revision->get($field)->getValue()[$paragraph_key];
        }
        $paragraph = $paragraph_storage->loadRevision($data_value['target_revision_id']);
        $data_value = [];
        if (!empty($paragraph->{$diff_field})) {
          $data_value = $paragraph->{$diff_field}->getValue();
        }

        $grouped_with = !empty($grouped_fields[$diff_field]) ? $grouped_fields[$diff_field]['grouped_with'] : $diff_field;
        if (empty($row['top']['summary'][$diff_field]['data']) && empty($row['top']['summary'][$grouped_with]['data'])) {
          continue;
        }
        if ($deleted) {
          $row['top']['summary'][$grouped_with]['data']['#markup'] = $this->t('Deleted');
          continue;
        }
        $paragraph_0 = $paragraph_storage->loadRevision($data_value_0['target_revision_id']);
        $data_value_0 = [];
        if (!empty($paragraph_0->{$diff_field})) {
          $data_value_0 = $paragraph_0->{$diff_field}->getValue();
        }

        $diffs = $diff[$diff_field];
        $diff_rows = $this->getDiffMarkup($diffs);

        $prefix = !empty($row['top']['summary'][$grouped_with]['data'][$diff_field]['#title'])
          ? $row['top']['summary'][$grouped_with]['data'][$diff_field]['#title']
          : NULL;

        unset($row['top']['summary'][$grouped_with]['data']['#markup']);

        $type = $this->getDiffFieldType($paragraph_form[$diff_field]['widget']);
        $copy_value_button = $this->getCopyValueButton($type, $data_value, $diff_field, $assessment_vid, $grouped_with);
        $init_button = $this->getCopyValueButton($type, $data_value_0, $diff_field, 0, $grouped_with);
        if (!in_array($diff_field, $grouped_with_fields)) {
          $row['top']['summary'][$grouped_with]['data'][$diff_field] = [
              '#type' => 'table',
              '#rows' => $diff_rows,
              '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
              '#prefix' => '<b>' . $prefix . '</b><div class="diff-wrapper">',
              '#suffix' => $copy_value_button . '</div>',
          ];
          $initial_copy_value_buttons[$grouped_with] = $init_button;
        }
      }

      $row['top']['summary']['author']['data']['#markup'] = $author;
      $form['widget'][] = $row;
    }
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';
    $form['widget']['#is_diff_form'] = TRUE;
    $form['widget']['edit'] = $form['widget'][$paragraph_key];

    $form['widget']['edit']['top']['summary']['author']['data']['#markup'] = '<b>' . t('Final version') . '</b>';
    $form['widget']['edit']['top']['#attributes']['class'][] = 'paragraph-diff-final';

    $display_mode = \Drupal::request()->query->get('display_mode');
    foreach (RowParagraphsWidget::getFieldComponents($paragraph_revision, $display_mode) as $field => $data) {
      $grouped_with = !empty($grouped_fields[$field]) ? $grouped_fields[$field]['grouped_with'] : $field;
      if (in_array($field, array_keys($paragraph_form))) {
        if (($field == 'field_as_threats_values_bio' || $field == 'field_as_threats_values_wh')
          && empty($paragraph_form[$field . '_select_wrapper']['#printed'])) {
          unset($paragraph_form[$field . '_select_wrapper'][$field . '_select']['#title']);
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field] = $paragraph_form[$field . '_select_wrapper'][$field . '_select'];
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field]['#parents'] = [$field . '_select'];
          unset($form['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          unset($paragraph_form[$field . '_select_wrapper']);
        }
        else {
          if (!empty($paragraph_form[$field]['widget']['#title'])) {
            $paragraph_form[$field]['widget']['#title_display'] = 'invisible';
          }
          if (!empty($paragraph_form[$field]['widget'][0]['value']['#title'])) {
            $paragraph_form[$field]['widget'][0]['value']['#title_display'] = 'invisible';
          }
          unset($form['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field] = $paragraph_form[$field];
          if ($field != $grouped_with) {
            $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$field]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($field) . '</b>';
            $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$grouped_with]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($grouped_with) . '</b>';
          }
        }
        unset($paragraph_form[$field]);
      }
    }

    unset($form['widget']['#element_validate']);

    $form['widget'][$paragraph_key]['#attributes']['class'][] = 'diff-original-row';
    foreach ($initial_copy_value_buttons as $grouped_with => $button) {
      $data = $form['widget'][$paragraph_key]['top']['summary'][$grouped_with];
      $form['widget'][$paragraph_key]['top']['summary'][$grouped_with] = [
        "#type" => $data['#type'],
        "#attributes" => $data['#attributes'],
        "#id" => $data['#id'],
      ];
      unset($data['#type']);
      unset($data['#attributes']);
      unset($data['#id']);
      $form['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data'] = $data;
      $form['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data']['#prefix'] = '<div class="diff-wrapper">';
      $form['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data']['#suffix'] = $button . '</div>';
    }

    $paragraph_form['diff'] = $form;
    $paragraph_form['diff']['#weight'] = 0;
    unset($paragraph_form['#fieldgroups']);

    $paragraph_form['#prefix'] = '<div class="diff-modal">';
    $paragraph_form['#suffix'] = '</div>';

    return $paragraph_form;
  }

}
