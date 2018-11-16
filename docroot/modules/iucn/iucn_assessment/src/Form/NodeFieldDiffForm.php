<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geysir\Ajax\GeysirCloseModalDialogCommand;
use Drupal\node\NodeInterface;

/**
 * Functionality to select a paragraph type.
 */
class NodeFieldDiffForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_assessment_field_diff_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="drupal-modal">';
    $form['#suffix'] = '</div>';
    $route_params = $form_state->getBuildInfo()['args'][0];
    $node = $route_params['node'];
    $field = $route_params['field'];
    $node_form = \Drupal::service('entity.form_builder')->getForm($node, 'default', []);
    $form = [];
    $form[$field] = $node_form[$field]['widget'];
    $form[$field] = !empty($node_form[$field]['widget'][0]['value']) ? $node_form[$field]['widget'][0]['value'] : $node_form[$field]['widget'];
    unset($form[$field]['diff']);
    unset($form[$field]['#value']);

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
      /** @var NodeInterface $revision */
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

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#attributes' => ['class' => ['use-ajax', 'button--primary']],
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => '::ajaxSave',
        'event' => 'click',
      ],
    ];
    IucnGeysirModalParagraphForm::buildCancelButton($form);
    return $form;
  }

  public function ajaxSave(&$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // When errors occur during form validation, show them to the user.
    if ($form_state->getErrors()) {
      unset($form['#prefix']);
      unset($form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#geysir-modal-form', $form));
    }
    else {
      $route_match = \Drupal::routeMatch();
      /** @var NodeInterface $node */
      $node = $route_match->getParameter('node');
      $field = $route_match->getParameter('field');
      $wrapper = $route_match->getParameter('field_wrapper_id');
      $node->get($field)->setValue($form_state->getValue($field));
      $node->save();

      $response->addCommand(
        new HtmlCommand(
          $wrapper,
          \Drupal::service('entity.form_builder')->getForm($node, 'default')[$field]
        )
      );
      $response->addCommand(new GeysirCloseModalDialogCommand());
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return [];
  }

}
