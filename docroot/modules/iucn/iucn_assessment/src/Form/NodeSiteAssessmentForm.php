<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Field\FieldFilteredMarkup;

class NodeSiteAssessmentForm {

  public static function hideUnnecessaryFields(array &$form) {
    // Hide unnecessary fields.
    unset($form['actions']['delete']);
    unset($form['advanced']);
    unset($form['revision']);
    unset($form['revision_log']);
    unset($form['author']);
    unset($form['meta']);
    $form['field_state']['#access'] = FALSE;
  }

  public static function addRedirectToAllActions(array &$form) {
    // Redirect to node edit on form submit.
    foreach ($form['actions'] as $key => &$action) {
      if (strpos($key, 'workflow_') !== FALSE || $key == 'submit') {
        $action['#submit'][] = [self::class, 'assessmentSubmitRedirect'];
      }
    }
  }

  /**
   * Recursive function used to used to unset the fields of a fieldgroup.
   */
  public static function removeGroupFields(&$form, $group) {
    foreach ($form['#fieldgroups'][$group]->children as $nested_field) {
      if (!empty($form[$nested_field]) && substr($nested_field, 0, 6) === 'field_') {
        $form[$nested_field]['#access'] = FALSE;
      }
      elseif (!empty($form['#fieldgroups'][$nested_field])) {
        self::removeGroupFields($form, $nested_field);
      }
    }
  }

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    $tab = \Drupal::request()->get('tab') ?: 'values';
    if (empty(\Drupal::request()->get('_wrapper_format'))
      || \Drupal::request()->get('_wrapper_format') != 'drupal_ajax') {
      // Unset the fields that are only present on other tabs.
      $group_tabs = $form['#fieldgroups']['group_as_tabs']->children;
      foreach ($group_tabs as $group_tab) {
        $fieldgroup_tab = $form['#fieldgroups'][$group_tab];
        $tab_id = str_replace('_', '-', $fieldgroup_tab->format_settings['id']);
        if ($tab_id != $tab) {
          self::removeGroupFields($form, $group_tab);
        }
      }
    }

    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;

    if ($state == AssessmentWorkflow::STATUS_PUBLISHED) {
      // Redirect the user to edit form of the draft assessment.
      $draft_revision = $workflow_service->getRevisionByState($node, AssessmentWorkflow::STATUS_DRAFT);
      if (!empty($draft_revision)) {
        $url = Url::fromRoute('node.revision_edit', ['node' => $node->id(), 'node_revision' => $draft_revision->getRevisionId()]);
      }
      else {
        $url = Url::fromRoute('iucn_assessment.node.state_change', ['node' => $node->id()]);
      }
      $response = new RedirectResponse($url->setAbsolute()->toString());
      $response->send();
    }

    self::hideUnnecessaryFields($form);
    self::addRedirectToAllActions($form);

    // On the values tab, only coordinators and above can edit the values.
    if (\Drupal::currentUser()->hasPermission('edit assessment main data') === FALSE) {
      if ($tab == 'values' || $tab == 'assessing-values') {
        self::hideParagraphsActions($form);
      }
      $form['title']['#disabled'] = TRUE;
      $form['langcode']['#disabled'] = TRUE;
      $form['field_as_start_date']['#access'] = FALSE;
      $form['field_as_end_date']['#access'] = FALSE;
      $form['field_date_published']['#access'] = FALSE;
      $form['field_assessment_file']['#access'] = FALSE;
    }

    // Hide key conservation issues for >2014 assessments.
    if ($node->field_as_cycle->value != 2014) {
      $form['field_as_key_cons']['#access'] = FALSE;
    }

    // Hide all revision related settings and check if a new revision should
    // be created in hook_node_presave().
    $form['revision']['#default_value'] = FALSE;
    $form['revision']['#disabled'] = FALSE;

    if (!empty($node->id()) && !empty($state)) {
      $state_entity = WorkflowState::load($state);
      $form['current_state'] = [
        '#weight' => -100,
        '#type' => 'markup',
        '#markup' => t('Current state: <b>@state</b>', ['@state' => $state_entity->label()]),
      ];
      $settings = json_decode($node->field_settings->value, TRUE);
      if (in_array($state, [AssessmentWorkflow::STATUS_UNDER_ASSESSMENT, AssessmentWorkflow::STATUS_UNDER_REVIEW])
        || !empty($settings['comments'][$tab])) {
        $current_user = \Drupal::currentUser();

        $fieldgroup_key = 'group_as_' . str_replace('-', '_', $tab);
        $comment_title = !empty($form['#fieldgroups'][$fieldgroup_key]->label)
          ? t('Comment about "@group"', ['@group' => $form['#fieldgroups'][$fieldgroup_key]->label])
          : t('Comment about current tab');
        $form["comment_$tab"] = [
          '#type' => 'textarea',
          '#title' => $comment_title,
          '#weight' => !empty($form['#fieldgroups'][$fieldgroup_key]->weight) ? $form['#fieldgroups'][$fieldgroup_key]->weight - 1 : 0,
          '#default_value' => !empty($settings['comments'][$tab][$current_user->id()]) ? $settings['comments'][$tab][$current_user->id()] : '',
          '#prefix' => '<div class="paragraph-comments-textarea">',
          '#suffix' => '</div>',
          '#description' => t('If you have any suggestions on this worksheet, leave a comment for the coordinator'),
        ];
        if (\Drupal::currentUser()->hasPermission('edit assessment main data')) {
          $form["comment_$tab"]['#attributes'] = ['readonly' => 'readonly'];
          unset($form["comment_$tab"]['#description']);
          $comments = '';
          if (!empty($settings['comments'][$tab])) {
            foreach ($settings['comments'][$tab] as $uid => $comment) {
              $comments .= '<b>' . User::load($uid)->getDisplayName() . ':</b> ' . $comment . "<br>";
            }
            $form["comment_$tab"]['#type'] = 'markup';
            $form["comment_$tab"]['#markup'] = $comments;
          }
          else {
            $form["comment_$tab"]['#access'] = FALSE;
          }
        }
        $form['#attached']['library'][] = 'iucn_assessment/paragraph_comments';
        $form['#attached']['library'][] = 'iucn_backend/font-awesome';
      }
    }

    if ($tab == 'assessing-values') {
      $form['field_as_values_wh']['widget']['#title'] = FieldFilteredMarkup::create('Assessing The Current State And Trend Of Values');
      $string = 'Assess the current state and trend of values for the World Heritage site. The current state of values is assessed against five ratings: Good, Low Concern, High Concern, Critical and Data Deficient). The baseline for the assessment should be the condition at the time of inscription, with reference to the best-recorded historical conservation state. Trend is assessed in relation to whether the condition of a value is Improving, Stable, Deteriorating or Data Deficient, and is intended to be a snapshot of recent developments over the last five years. The \'Justification for assessment\' must be systematically referenced, e.g. (SOC report, 2009).';
      $form['field_as_values_wh']['widget']['#description'] = FieldFilteredMarkup::create($string);
    }

    if ($tab == 'assessing-values' && !empty($form['field_as_values_bio']['widget']["#max_delta"]) && $form['field_as_values_bio']['widget']["#max_delta"] == -1) {
      hide($form['field_as_vass_bio_state']);
      hide($form['field_as_vass_bio_text']);
      hide($form['field_as_vass_bio_trend']);
    }

    if (in_array($tab, ['threats', 'protection-management', 'assessing-values', 'conservation-outlook'])) {
      $form['overall_table_thead'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['overall-row', 'overall-thead-row']],
        '#weight' => -100,
        'topic_justification' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['overall-cell', 'overall-textarea']],
          'topic' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => t('Topic'),
          ],
          'justification' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['form-textarea-wrapper']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => t('Justification'),
            ],
          ],
        ],
        'assessment' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['overall-cell', 'overall-cell-rating']],
          '#value' => t('Assessment'),
        ],
      ];
      if ($tab == 'assessing-values') {
        $form['overall_table_thead']['topic_justification']['topic']['#value'] = t('Value');
        $form['overall_table_thead']['trend'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['overall-cell', 'overall-cell-trend']],
          '#value' => t('Trend'),
        ];
      }
    }

    if (!empty($form['overall_table_thead'])) {
      $container_group = 'group_' . substr($tab, 0, strpos($tab, '-') ?: 1000) . '_overall_container';
      if (!empty($form['#fieldgroups'][$container_group])) {
        $form['#fieldgroups'][$container_group]->children[] = 'overall_table_thead';
        $form['#group_children']['overall_table_thead'] = $container_group;
      }
    }

    array_unshift($form['actions']['submit']['#submit'], [self::class, 'setAssessmentSettings']);

    self::buildDiffButtons($form, $node);

    // Hide these fields if there are no other biodiversity values.
    if ($tab == 'protection-management' && empty($node->field_as_values_bio->getValue())) {
      $fields = [
        'field_as_protection_ov_out_rate',
        'field_as_protection_ov_out_text',
        'field_as_protection_ov_practices',
        'field_as_protection_ov_rating',
        'field_as_protection_ov_text',
      ];
      foreach ($fields as $field) {
        unset($form[$field]);
      }

      $form['#fieldgroups']['group_protection_overall_container']->format_settings['classes'] = 'hidden-container';
    }

    if ($tab == 'benefits') {
      $form['#validate'][] = [self::class, 'benefitsValidation'];
    }
  }

  public static function benefitsValidation(array $form, FormStateInterface $form_state) {
    $node = $form_state->getFormObject()->getEntity();
    if (!empty($node->field_as_benefits->getValue()) && empty($form_state->getValue('field_as_benefits_summary')['value'])) {
      $form_state->setErrorByName('summary_of_benefits', t('Summary of benefits is mandatory'));
    }
    if (in_array($node->field_state->value, AssessmentWorkflow::DIFF_STATES)) {
      self::buildDiffButtons($form, $node);
      self::setTabsDrupalSettings($form, $node);
    }
  }

  public static function setTabsDrupalSettings(&$form, $node) {
    $diff = self::getNodeDiff($node);
    if (empty($diff)) {
      return;
    }
    $diff_tabs = [];
    foreach ($diff as $vid => $diff_data) {
      if (empty($diff_data['fieldgroups'])) {
        continue;
      }
      $diff_tabs += $diff_data['fieldgroups'];
    }
    $form['#attached']['drupalSettings']['iucn_assessment']['diff_tabs'] = $diff_tabs;
  }

  /*
   *
   * Store comments on node.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function setAssessmentSettings(&$form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $values = $form_state->getValues();

    $settings = json_decode($node->field_settings->value, TRUE);
    foreach ($values as $key => $value) {
      if (preg_match('/^comment\_(.+)$/', $key, $matches) && !empty(trim($value))) {
        $commented_tab = $matches[1];
        $settings['comments'][$commented_tab][$current_user->id()] = $value;
      }
    }
    $node->field_settings->setValue(json_encode($settings));
    $nodeForm->setEntity($node);
    $form_state->setFormObject($nodeForm);
  }

  /**
   * Get the markup for the current state label.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The assessment.
   * @param int $weight
   *   The weight of the state label.
   *
   * @return array
   *   The renderable array.
   */
  public static function getCurrentStateMarkup(NodeInterface $node, $weight = -1000) {
    $current_state = $node->field_state->value;
    if (!empty($current_state)) {
      $state_entity = WorkflowState::load($current_state);
    }
    else {
      $state_entity = NULL;
    }
    $state_label = !empty($state_entity) ? $state_entity->label() : 'Creation';
    return [
      '#weight' => $weight,
      '#type' => 'markup',
      '#markup' => t('Current state: <b>@state</b>', ['@state' => $state_label]),
    ];
  }

  /**
   * Submit callback for the state change form.
   *
   * Redirects the user to the assessment edit page if he can access it.
   * Otherwise, this will redirect the user to /user.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public static function assessmentSubmitRedirect(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $tab = \Drupal::request()->query->get('tab');
    $options = [];
    if (!empty($tab)) {
      $options = ['query' => ['tab' => $tab]];
    }
    if ($workflow_service->checkAssessmentAccess($node, 'edit')->isAllowed()) {
      if (!$node->isDefaultRevision()) {
        $form_state->setRedirect('node.revision_edit', ['node' => $node->id(), 'node_revision' => $node->getRevisionId()], $options);
      }
      else {
        $form_state->setRedirectUrl($node->toUrl('edit-form', $options));
      }
    }
    elseif ($workflow_service->checkAssessmentAccess($node, 'change_state')->isAllowed()) {
      $form_state->setRedirect('iucn_assessment.node.state_change', ['node' => $node->id()]);
    }
    else {
      $form_state->setRedirect('who.user-dashboard');
    }
  }

  /**
   * Hide all paragraphs actions on a form.
   *
   * @param array $form
   *   The form.
   */
  public static function hideParagraphsActions(array &$form) {
    $read_only_paragraph_fields = ['field_as_values_bio', 'field_as_values_wh'];
    foreach ($read_only_paragraph_fields as $field) {
      if (!empty($form[$field]['widget'])) {
        self::hideParagraphsActionsFromWidget($form[$field]['widget']);
      }
    }
  }

  /**
   * Hide all paragraphs actions from a widget.
   *
   * @param array $widget
   *   The widget.
   * @param bool $alter_colspan
   *   Is the colspan of the table altered.
   */
  public static function hideParagraphsActionsFromWidget(array &$widget, $alter_colspan = TRUE) {
    $widget['add_more']['#access'] = FALSE;
    if (!empty($widget['header']['data']['actions'])) {
      $widget['header']['data']['actions']['#access'] = FALSE;
      if ($alter_colspan) {
        $classes = &$widget['header']['#attributes']['class'];
        foreach ($classes as &$class) {
          if (preg_match('/paragraph-top-col-(.*)/', $class, $matches)) {
            $col_number = $matches[1];
            $col_class = $class;
            $new_col_number = $col_number - 1;
            $new_col_class = "paragraph-top-col-$new_col_number";
            $class = $new_col_class;
          }
        }
      }
    }
    foreach ($widget as $key => &$paragraph) {
      if (!is_int($key)) {
        continue;
      }
      $paragraph['top']['actions']['#access'] = FALSE;
      if ($alter_colspan) {
        $classes = &$paragraph['top']['#attributes']['class'];
        if (!empty($new_col_class)) {
          foreach ($classes as &$class) {
            if ($class == $col_class) {
              $class = $new_col_class;
            }
          }
        }
      }
    }
  }

  public static function buildDiffButtons(&$form, $node) {
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.field_diff';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $diff = self::getNodeDiff($node);
    if (empty($diff)) {
      return;
    }
    foreach ($form as $field => &$form_item) {
      if (!self::isFieldWithDiff($node, $field, $diff)) {
        continue;
      }
      $diff_button = self::getFieldDiffButton($node, $field);
      $form[$field]['diff'] = $diff_button;
      $form[$field]['#attributes']['class'][] = 'field-with-diff';
    }
  }

  public static function getNodeDiff($node) {
    $settings = $node->field_settings->value;
    if (empty($settings)) {
      return NULL;
    }
    $settings = json_decode($settings, TRUE);
    if (empty($settings['diff'])) {
      return NULL;
    }
    return $settings['diff'];
  }

  public static function isFieldWithDiff($node, $field, $diff) {
    if (substr($field, 0, 6) !== 'field_') {
      return FALSE;
    }
    foreach (array_keys($diff) as $vid) {
      if (!empty($diff[$vid]['node'][$node->id()]['diff'][$field])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function getFieldDiffButton(NodeInterface $node, $field) {
    return [
      '#type' => 'submit',
      '#value' => 'See differences',
      '#weight' => 2,
      '#ajax' => [
        'event' => 'click',
        'url' => Url::fromRoute('iucn_assessment.field_diff_form', [
          'node' => $node->id(),
          'node_revision' => $node->getRevisionId(),
          'field' => $field,
          'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field) . '-wrapper',
        ]),
        'progress' => [
          'type' => 'fullscreen',
          'message' => NULL,
        ],
      ],
      '#attributes' => [
        'class' => [
          'paragraphs-icon-button',
          'paragraphs-icon-button-compare',
          'use-ajax',
          'field-diff',
        ],
        'title' => t('See differences'),
      ],
    ];
  }

}
