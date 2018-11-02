<?php

namespace Drupal\iucn_who_diff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
/**
 * IucnDiffModalForm class.
 */
class IucnDiffModalForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_diff_modal_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    // Set the hard-coded theme for the diff prototype
    $type = 'value';
    $form = [
      '#theme' => 'diff_prototype_modal',
      '#values' => !empty($type) ? $type : '',
    ];

    $form['#prefix'] = '<div id="iucn_diff_modal_form">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    // Attach the libraries
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'paragraphs/drupal.paragraphs.widget';
    $form['#attached']['library'][] = 'paragraphs/drupal.paragraphs.actions';
    $form['#attached']['library'][] = 'diff/diff.general';
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['#attached']['library'][] = 'diff/diff.single_column';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $form['#attached']['library'][] = 'iucn_who_diff/iucn_who_diff.diff_prototype';

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit modal form'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $chosen_conf = \Drupal::config('chosen.settings');

    $css_disabled_themes = $chosen_conf->get('disabled_themes');
    if (empty($css_disabled_themes)) {
      $css_disabled_themes = [];
    }

    $theme_name = \Drupal::theme()->getActiveTheme()->getName();
    if (!in_array($theme_name, $css_disabled_themes, TRUE)) {
      $build['#attached']['library'][] = 'chosen_lib/chosen.css';
    }

    return $form;
  }
  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#iucn_diff_modal_form', $form));
    }
    else {
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 700]));
    }
    return $response;
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}
  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.modal_form_example_modal_form'];
  }
}