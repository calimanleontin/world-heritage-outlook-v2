<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Field\FieldFilteredMarkup;

class NodeSiteAssessmentForm {

  use AssessmentEntityFormTrait;

  public static function setValidationErrors(&$form, $element, $parents) {
    $children = Element::children($element);
    foreach ($children as $idx => $child) {
      if (!empty($element[$child]['#type']) && $element[$child]['#type'] != 'hidden') {
        $form['actions']['submit']['#limit_validation_errors'][] = array_merge($parents, [$child]);
      }

      if (is_array($element[$child])) {
        self::setValidationErrors($form, $element[$child], array_merge($parents, [$child]));
      }
    }
  }

  public static function prepareForm(NodeInterface $node, $operation, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $formObject */
    $formObject = $form_state->getFormObject();

    // The revision edit page is actually the node edit page.
    // We need to change the form entity to the selected revision.
    $node_revision = \Drupal::routeMatch()->getParameter('node_revision');
    if (!empty($node_revision)) {
      $node = $node_revision;
    }

    $formDisplay = $formObject->getFormDisplay($form_state);

    $group_as_tabs = $formDisplay->getThirdPartySetting('field_group', 'group_as_tabs');
    if (!empty($group_as_tabs['children'])) {
      $tab = \Drupal::request()->get('tab') ?: 'values';
      foreach ($group_as_tabs['children'] as $group_tab) {
        $fieldGroupTab = $formDisplay->getThirdPartySetting('field_group', $group_tab);
        $tab_id = str_replace('_', '-', $fieldGroupTab['format_settings']['id']);
        if ($tab_id == $tab) {
          continue;
        }
        self::removeGroupFields($formDisplay, $fieldGroupTab);
      }
    }
    $formObject->setFormDisplay($formDisplay, $form_state);
    $formObject->setEntity($node);
    $form_state->setFormObject($formObject);
  }

  /**
   * Recursive function used to used to unset the fields of a fieldgroup.
   */
  public static function removeGroupFields(EntityFormDisplayInterface $formDisplay, $fieldGroupTab) {
    foreach ($fieldGroupTab['children'] as $child) {
      $formDisplay->removeComponent($child);

      $childFieldGroupTab = $formDisplay->getThirdPartySetting('field_group', $child);
      if (!empty($childFieldGroupTab)) {
        self::removeGroupFields($formDisplay, $childFieldGroupTab);
      }
    }
  }

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    $tab = \Drupal::request()->get('tab') ?: 'values';

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
      if ($tab == 'values') {
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
      $form['current_state'] = self::getCurrentStateMarkup($node);
      if (!empty($form['title']) && !empty($form['langcode']) && !empty($form['field_assessment_file'])) {
        $form['main_data_container'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['main-data-container']],
          '#weight' => -999,
          'data' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['data-fields']],
            'title' => $form['title'],
            'langcode' => $form['langcode'],
            'field_assessment_file' => $form['field_assessment_file'],
          ],
        ];
        unset($form['title']);
        unset($form['langcode']);
        unset($form['field_assessment_file']);
      }

      $blockContent = BlockContent::load(8);
      if (!empty($blockContent)) {
        $form['main_data_container']['help'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['help-text']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => t('Help'),
          ],
          'help' => \Drupal::entityTypeManager()->getViewBuilder('block_content')->view($blockContent),
        ];
      }

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
          '#maxlength' => 255,
        ];
        if (\Drupal::currentUser()->hasPermission('edit assessment main data')) {
          $form["comment_$tab"]['#attributes'] = ['readonly' => 'readonly'];
          unset($form["comment_$tab"]['#description']);
          $comments = '';
          if (!empty($settings['comments'][$tab])) {
            foreach ($settings['comments'][$tab] as $uid => $comment) {
              $comment = '<div class="comment-comments"><div class="comment-text">' . $comment . '</div></div>';
              $comment = str_replace("\r\n", '</div><div class="comment-text">', $comment);

              $comments .= '<div class="comments-container"><div class="comment-author">' . User::load($uid)->getDisplayName() . ':</div>' . $comment . '</div>';
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
    if (!empty($form['field_as_global_assessment_level'])) {
      // Exclude coming soon.
      $tid = 1420;
      if (!in_array($tid, $form['field_as_global_assessment_level']['widget']['#default_value'])) {
        unset($form['field_as_global_assessment_level']['widget']['#options'][$tid]);
      }
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
              '#value' => t('Justification of assessment'),
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
      elseif ($tab == 'protection-management') {
        $fieldAsProtectionWidget = &$form['field_as_protection']['widget'];
        foreach (Element::children($fieldAsProtectionWidget) as $child) {
          if (empty($fieldAsProtectionWidget[$child]['#paragraph_id'])) {
            continue;
          }
          $paragraph = Paragraph::load($fieldAsProtectionWidget[$child]['#paragraph_id']);
          /** @var \Drupal\taxonomy\TermInterface $protectionTopic */
          $protectionTopic = $paragraph->field_as_protection_topic->entity;
          if (empty($protectionTopic)) {
            continue;
          }
          $fieldAsProtectionWidget[$child]['#delta'] = $protectionTopic->getWeight();
          $fieldAsProtectionWidget[$child]['#weight'] = $protectionTopic->getWeight();
          $fieldAsProtectionWidget[$child]['_weight']['#default_value'] = $protectionTopic->getWeight();
        }

        $fieldAsProtectionBestPracticeWidget = &$form['field_as_protection_ov_practices']['widget'][0];
        $title = [
          '#theme' => 'topic_tooltip',
          '#label' => t('Best Practice Examples'),
          '#help_text' => t('Tooltip. Text needs to be provided.'),
        ];
        $fieldAsProtectionBestPracticeWidget['#title'] = render($title);

      }
    }

    if (!empty($form['overall_table_thead'])) {
      $container_group = 'group_' . substr($tab, 0, strpos($tab, '-') ?: 1000) . '_overall_container';
      if (!empty($form['#fieldgroups'][$container_group])) {
        $form['#fieldgroups'][$container_group]->children[] = 'overall_table_thead';
        $form['#group_children']['overall_table_thead'] = $container_group;
      }
    }

    // Hide these fields if there are no other biodiversity values.
    if ($tab == 'assessing-values' && empty($node->field_as_values_bio->getValue())) {
      $fields = [
        'field_as_vass_bio_state',
        'field_as_vass_bio_text',
        'field_as_vass_bio_trend',
      ];
      foreach ($fields as $field) {
        $form[$field]['#access'] = FALSE;
      }
    }

    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.select_options_colors';
    $form['#attached']['drupalSettings']['terms_colors'] = _iucn_assessment_get_term_colors();
    // Validation.
    if ($tab == 'benefits') {
      $form['#validate'][] = [self::class, 'benefitsValidation'];
      if (!empty($node->field_as_benefits->getValue())) {
        $form['field_as_benefits_summary']['widget'][0]['value']['#required'] = TRUE;
      }
    }
    elseif ($tab == 'assessing-values') {
      if (!empty($node->field_as_values_bio->getValue())) {
        $required_fields = [
          'field_as_vass_bio_text',
          'field_as_vass_bio_state',
          'field_as_vass_bio_trend',
        ];
        foreach ($required_fields as $field) {
          if (!empty($form[$field]['widget'][0]['value'])) {
            $form[$field]['widget'][0]['value']['#required'] = TRUE;
          }
          elseif (!empty($form[$field]['widget'][0])) {
            $form[$field]['widget'][0]['#required'] = TRUE;
          }
          elseif (!empty($form[$field]['widget'])) {
            $form[$field]['widget']['#required'] = TRUE;
          }
          else {
            $form[$field]['#required'] = TRUE;
          }
        }
      }
    }

    if (in_array($node->field_state->value, AssessmentWorkflow::DIFF_STATES)) {
      self::buildDiffButtons($form, $node);
      self::setTabsDrupalSettings($form, $node);
    }

    self::setValidationErrors($form, $form, []);

    if (empty($node->id())) {
      // We allow users to create nodes without child paragraphs.
      $allowedFields = ['field_as_site', 'field_assessment_file'];
      $form = array_filter($form, function ($key) use ($allowedFields) {
        return !preg_match('/^field\_/', $key) || in_array($key, $allowedFields);
      }, ARRAY_FILTER_USE_KEY);
      unset($form['#fieldgroups']);
    }
    else {
      // Hide the site field because it is in the title.
      unset($form['field_as_site']);
    }

    array_unshift($form['actions']['submit']['#submit'], [self::class, 'setAssessmentSettings']);
  }

  public static function benefitsValidation(array $form, FormStateInterface $form_state) {
    $node = $form_state->getFormObject()->getEntity();
    if (!empty($node->field_as_benefits->getValue()) && empty($form_state->getValue('field_as_benefits_summary')[0]['value'])) {
      $form_state->setErrorByName('field_as_benefits_summary', t('Summary of benefits field is required'));
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

  /**
   * Store comments on node and set the current user as coordinator for NEW assessments.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function setAssessmentSettings(&$form, FormStateInterface $form_state) {
    $currentUser = \Drupal::currentUser();
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $values = $form_state->getValues();

    if ($node->isDefaultRevision()
      && $workflowService->isNewAssessment($node)
      && empty($node->field_coordinator->target_id)
      && in_array('coordinator', $currentUser->getRoles())) {
      // Sets the current user as a coordinator if he has the coordinator role
      // and edits the assessment.
      $oldState = $node->field_state->value;
      $newState = AssessmentWorkflow::STATUS_UNDER_EVALUATION;
      $node->set('field_coordinator', ['target_id' => $currentUser->id()]);
      $workflowService->createRevision($node, $newState, $currentUser->id(), "{$oldState} ({$node->getRevisionId()}) => {$newState}", TRUE);
    }

    $settings = json_decode($node->field_settings->value, TRUE);
    foreach ($values as $key => $value) {
      if (preg_match('/^comment\_(.+)$/', $key, $matches) && !empty(trim($value))) {
        $commented_tab = $matches[1];
        $settings['comments'][$commented_tab][$currentUser->id()] = $value;
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
  public static function getCurrentStateMarkup(NodeInterface $node) {
    $current_state = $node->field_state->value;
    if (!empty($current_state)) {
      $state_entity = WorkflowState::load($current_state);
    }
    else {
      $state_entity = NULL;
    }
    if (empty($state_entity)) {
      return [];
    }
    $state_label = !empty($state_entity) ? $state_entity->label() : 'Creation';
    return [
      '#weight' => -1000,
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['current-state']],
      '#value' => t('Current workflow state: <b>@state</b>', ['@state' => $state_label]),
    ];
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
      '#type' => 'link',
      '#title' => t('See differences'),
      '#weight' => 2,
      '#url' => Url::fromRoute('iucn_assessment.field_diff_form', [
        'node' => $node->id(),
        'node_revision' => $node->getRevisionId(),
        'field' => $field,
        'field_wrapper_id' => '#edit-' . str_replace('_', '-', $field) . '-wrapper',
      ]),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
          'field-icon-button',
          'field-icon-button-compare',
        ],
        'data-dialog-type' => 'modal',
        'title' => t('See differences'),
      ],
    ];
  }

}
