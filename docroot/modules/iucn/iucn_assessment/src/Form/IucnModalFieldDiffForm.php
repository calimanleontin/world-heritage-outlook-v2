<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalFieldDiffForm extends IucnModalForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node_revision = $this->getRouteMatch()->getParameter('node_revision');
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->loadRevision($node_revision);
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
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $workflow_service->getAssessmentRevision($assessment_vid);
      $diff_data = $diff[$node->id()]['diff'][$field];
      $row = [];
      $row['author'] = $revision->getRevisionUser()->getDisplayName();
      $row['diff'] = ['data' => []];
      $diff_rows = [];

      foreach ($diff_data as $diff_group) {
        for ($i = 0; $i < count($diff_group); $i += 2) {
          $diff_rows[] = [$diff_group[$i], $diff_group[$i + 1]];
        }
      }

      $row['diff']['data'] = [
        '#type' => 'table',
        '#rows' => $diff_rows,
        '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
      ];

      $diff_table['#rows'][] = $row;
    }
    $form['diff'] = $diff_table;
    $form['#attached']['library'][] = 'diff/diff.colors';

    $this->buildCancelButton($form);
    return $form;
  }

}
