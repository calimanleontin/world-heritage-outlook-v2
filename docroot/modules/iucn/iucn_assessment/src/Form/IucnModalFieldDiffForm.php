<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Controller\ModalDiffController;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\user\Entity\User;

class IucnModalFieldDiffForm extends IucnModalForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node = $this->getRouteMatch()->getParameter('node_revision');
    $field = $this->getRouteMatch()->getParameter('field');
    $parent_form = parent::buildForm($form, $form_state);

    $diff_table = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $this->t('Difference')],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];

    $form = [];
    unset($parent_form[$field]['diff']);
    $form[$field] = $parent_form[$field];

    $row = [];
    $row['author']['data']['#markup'] = '<b>' . $this->t('Initial version') . '</b>';
    $diff_table['#rows'][] = $row;


    $form['actions'] = $parent_form['actions'];
    $form['#prefix'] = '<div id="drupal-modal" class="diff-modal">';
    $form['#suffix'] = '</div>';

    $settings = $node->field_settings->value;
    $settings = json_decode($settings, TRUE);

    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    foreach ($settings['diff'] as $assessment_vid => $diff) {
      if (empty($diff['node'][$node->id()]['diff'][$field])) {
        continue;
      }
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $workflow_service->getAssessmentRevision($assessment_vid);
      $diff_data = $diff['node'][$node->id()]['diff'][$field];
      $row = [];
      $row['author'] = $node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW
        ? $node->field_assessor->entity->getDisplayName()
        : $revision->getRevisionUser()->getDisplayName();

      $row['diff'] = ['data' => []];
      $diff_rows = ModalDiffController::getDiffMarkup($diff_data);

      if ($node->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        $data_value = $node->get($field)->getValue();
        $data_value_0 = $revision->get($field)->getValue();
        $value_0 = $revision->get($field)->view(['settings' => ['link' => 0]]);
      }
      else {
        $data_value_0 = $node->get($field)->getValue();
        $value_0 = $node->get($field)->view(['settings' => ['link' => 0]]);
        $data_value = $revision->get($field)->getValue();
      }
      if ($value_0) {
        unset($value_0['#title']);
      }
      $type = $this->get_diff_field_type($form, $field);
      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $diff_rows,
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        '#prefix' => '<div class="diff-wrapper">',
        '#suffix' => $this->get_copy_value_button($form, $type, $data_value, $field, $assessment_vid, $field) . '</div>',
      ];
      $diff_table['#rows'][] = $row;
      $init_button = $this->get_copy_value_button($form, $type, $data_value_0, $field, 0, $field);
      $diff_table['#rows'][0]['diff']['data'] = $value_0;
      $diff_table['#rows'][0]['diff']['data']['#prefix'] = '<div class="diff-wrapper">';
      $diff_table['#rows'][0]['diff']['data']['#suffix'] = $init_button . '</div>';
    }

    $form['diff'] = $diff_table;
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';

    self::buildCancelButton($form);
    unset($form['actions']['delete']);
    return $form;
  }

}
