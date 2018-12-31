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

    $form = [];
    $form[$field] = $parent_form[$field];
    $form['actions'] = $parent_form['actions'];
    $form['#prefix'] = '<div id="drupal-modal">';
    $form['#suffix'] = '</div>';
    unset($form[$field]['diff']);

    $settings = $node->field_settings->value;
    $settings = json_decode($settings, TRUE);
    $diff_table = [
      '#type' => 'table',
      '#header' => [$this->t('Author'), $this->t('Difference')],
      '#rows' => [],
      '#weight' => -10,
      '#attributes' => ['class' => ['field-diff-table']],
    ];
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

      $data_value = $revision->get($field)->getValue();//[$paragraph_key];
      $type = $this->get_diff_field_type($form, $field);
      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $diff_rows,
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        '#prefix' => '<div class="diff-wrapper">',
        '#suffix' => $this->get_copy_value_button($form, $type, $data_value, $field, $assessment_vid).'</div>',
      ];

      $diff_table['#rows'][] = $row;
    }
    $form['diff'] = $diff_table;
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.paragraph_diff';

    self::buildCancelButton($form);
    unset($form['actions']['delete']);
    return $form;
  }

}
