<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Field\FieldFilteredMarkup;

class NodeSiteAssessmentForm {

  use AssessmentEntityFormTrait;

  const DEPENDENT_FIELDS = [
    'field_as_threats_potential' => [
      'field_as_threats_potent_text',
      'field_as_threats_potent_rating',
    ],
  ];

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
    $currentRoute = \Drupal::routeMatch()->getRouteName();
    $tab = \Drupal::request()->get('tab') ?: 'values';

    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $state = $node->field_state->value;

    if ($currentRoute == 'entity.node.edit_form' && $node->isDefaultTranslation() && $state == AssessmentWorkflow::STATUS_PUBLISHED) {
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

    $readOnly = \Drupal::routeMatch()->getRouteObject()->getOption('_read_only_form');
    if ($readOnly == TRUE) {
      $form['actions']['#access'] = FALSE;
      $form['langcode']['#disabled'] = TRUE;
    }

    self::hideParagraphsActions($form, $node);
    if (\Drupal::currentUser()->hasPermission('edit assessment main data') === FALSE) {
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

      $settings = json_decode($node->field_settings->value, TRUE);
      if (in_array($state, [
          AssessmentWorkflow::STATUS_UNDER_ASSESSMENT,
          AssessmentWorkflow::STATUS_UNDER_REVIEW,
          AssessmentWorkflow::STATUS_REVIEWING_REFERENCES,
        ]) || !empty($settings['comments'][$tab])) {
        $current_user = \Drupal::currentUser();

        $form['comments'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['comments-container']],
        ];

        $fieldgroup_key = 'group_as_' . str_replace('-', '_', $tab);
        $form['comments']['comment'] = [
          '#type' => 'textarea',
          '#weight' => !empty($form['#fieldgroups'][$fieldgroup_key]->weight) ? $form['#fieldgroups'][$fieldgroup_key]->weight + 1 : 0,
          '#default_value' => !empty($settings['comments'][$tab][$current_user->id()]) ? $settings['comments'][$tab][$current_user->id()] : '',
          '#prefix' => '<div class="paragraph-comments-textarea">',
          '#suffix' => '</div>',
          '#maxlength' => 5000,
          '#tab' => $tab,
          '#parents' => ['comments'],
        ];
        if (\Drupal::currentUser()->hasPermission('edit assessment main data')) {
          $form['comments']['#type'] = 'fieldset';
          $form['comments']['#title'] = t('Comments');

          $form['comments']['comment']['#type'] = 'markup';
          $form['comments']['comment']['#markup'] = t('No comments added yet');
          if (!empty($settings['comments'][$tab])) {
            $comments = '';
            foreach ($settings['comments'][$tab] as $uid => $comment) {
              $comment = '<div class="comment-comments"><div class="comment-text">' . $comment . '</div></div>';
              $comment = str_replace("\r\n", '</div><div class="comment-text">', $comment);
              $comments .= '<div class="comments-container"><div class="comment-author">' . User::load($uid)->getDisplayName() . ':</div>' . $comment . '</div>';
            }
            $form['comments']['comment']['#markup'] = $comments;
          }
        }
        else {
          $form['comments']['help'] = [
            '#markup' => '<div class="comments-help"><div><b>' . t('Internal comments on worksheet changes for IUCN?') .  '</b></div><div>' . t('Use the space below if you wish to provide comments on your worksheet changes to IUCN. These comments will be visible to IUCN but are not part of the conservation outlook assessment/will not be made publicly available.') . '</div></div>',
          ];
        }
      }
    }

    if ($tab == 'assessing-values') {
      $form['field_as_values_wh']['widget']['#title'] = FieldFilteredMarkup::create('Assessing The Current State And Trend Of Values');
      $string = 'Assess the current state and trend of values identified in Step 1 for the World Heritage site. The current state of values is assessed against five ratings: Good, Low Concern, High Concern, Critical and Data Deficient; see <a href="/modules/iucn/iucn_assessment/data/guidelines/Guidelines%20-%20IUCN%20Conservation%20Outlook%20Assessments%20Version%203.1.pdf#page=27" target="_blank">Table 4.1 of the Guidelines</a>). The baseline for the assessment should be the condition at the time of inscription, with reference to the best-recorded historical conservation state. To the extent possible, the assessment should be based on available quantitative information (e.g. current population size of key species; landscape features affected by a certain threat; level of intactness of key geological features). Trend is assessed in relation to whether the condition of a value is Improving, Stable, Deteriorating or Data Deficient, and is intended to be a snapshot of recent developments over the last five years. The ‘Justification of assessment’ must be systematically referenced, e.g. (IUCN, 2019), using IUCN’s <a href="/modules/iucn/iucn_assessment/data/guidelines/Guidelines%20-%20IUCN%20Conservation%20Outlook%20Assessments%20Version%203.1.pdf#page=33" target="_blank">referencing style</a> for Conservation Outlook Assessments. <a href="/modules/iucn/iucn_assessment/data/guidelines/Guidelines%20-%20IUCN%20Conservation%20Outlook%20Assessments%20Version%203.1.pdf#page=26" target="_blank">Access Guidelines for this step.</a>';
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
        $fieldAsProtectionBestPracticeWidget = &$form['field_as_protection_ov_practices']['widget'][0];
        $title = [
          '#theme' => 'topic_tooltip',
          '#label' => t('Best Practice Examples'),
          '#help_text' => t('Where relevant, note best-practice examples including a short explanation of why they are considered to be best practice and key lessons learned that could be replicated in other sites. All best-practice examples should be specific and focused on concrete management aspects and should be referenced.'),
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

    if (in_array($node->field_state->value, AssessmentWorkflow::DIFF_STATES)) {
      self::buildDiffButtons($form, $node);
      self::setTabsDrupalSettings($form, $node);
    }

    self::setValidationErrors($form, $form, []);

    $form['main_data_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['main-data-container']],
      '#weight' => -999,
      'data' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['data-fields']],
      ],
    ];
    $mainDataFields = [
      'title',
      'langcode',
      'field_assessment_file',
      'field_as_site',
      'field_as_cycle',
    ];
    foreach ($mainDataFields as $field) {
      if (empty($form[$field])) {
        continue;
      }
      $form['main_data_container']['data'][$field] = $form[$field];
      unset($form[$field]);
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

    if (empty($node->id())) {
      // We allow users to create nodes without child paragraphs.
      $allowedFields = [
        'field_as_site',
        'field_as_cycle',
        'field_assessment_file',
        ];
      $form = array_filter($form, function ($key) use ($allowedFields) {
        return !preg_match('/^field\_/', $key) || in_array($key, $allowedFields);
      }, ARRAY_FILTER_USE_KEY);
      unset($form['#fieldgroups']);
    }
    else {
      // Hide the site field because it is in the title.
      unset($form['field_as_site']);
      unset($form['field_as_cycle']);
    }

    static::alterFieldsRestrictions($tab, $form, $node);
    if ($tab == 'benefits') {
      $form['#validate'][] = [self::class, 'benefitsValidation'];
    }

    array_unshift($form['actions']['submit']['#submit'], [self::class, 'setAssessmentSettings']);
    $form['actions']['submit']['#submit'][] = [self::class, 'createCoordinatorRevision'];

    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.select_options_colors';
    $form['#attached']['drupalSettings']['terms_colors'] = _iucn_assessment_get_term_colors();
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.chrome_alert';
    $form['#attached']['library'][] = 'iucn_assessment/iucn_assessment.unsaved_warning';
  }

  public static function benefitsValidation(array $form, FormStateInterface $form_state) {
    $node = $form_state->getFormObject()->getEntity();
    if (!empty($node->field_as_benefits->getValue()) && empty($form_state->getValue('field_as_benefits_summary')[0]['value'])) {
      $form_state->setErrorByName('field_as_benefits_summary', t('Summary of benefits field is required'));
    }
  }

  public static function setTabsDrupalSettings(&$form, $node) {
    $diff = static::getNodeSettings($node, 'diff');
    $diff_tabs = [];
    foreach ($diff as $vid => $diff_data) {
      if (empty($diff_data['fieldgroups'])) {
        continue;
      }
      $diff_tabs += $diff_data['fieldgroups'];
    }

    $comments = static::getNodeSettings($node, 'comments');

    foreach ($comments as $tab => $comment) {
      $diff_tabs += [$tab => $tab];
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

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();
    $values = $form_state->getValues();

    $settings = json_decode($node->field_settings->value, TRUE);

    if (isset($values['comments'])) {
      $tagName = $form['comments']['comment']['#tab'];
      $settings['comments'][$tagName][$currentUser->id()] = $values['comments'];
      if (empty(array_filter($settings['comments'][$tagName]))) {
        unset($settings['comments'][$tagName]);
      }

      if (empty(array_filter($settings['comments']))) {
        unset($settings['comments']);
      }
    }

    $node->field_settings->setValue(json_encode($settings));
    $nodeForm->setEntity($node);
    $form_state->setFormObject($nodeForm);
  }

  /**
   * Sets the current user as a coordinator if he has the coordinator role and
   * is the first one who edits the assessment.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createCoordinatorRevision(&$form, FormStateInterface $form_state) {
    $currentUser = \Drupal::currentUser();
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflowService */
    $workflowService = \Drupal::service('iucn_assessment.workflow');

    /** @var \Drupal\node\NodeForm $nodeForm */
    $nodeForm = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeForm->getEntity();

    if ($node->isDefaultRevision()
      && $workflowService->isNewAssessment($node)
      && empty($node->field_coordinator->target_id)
      && in_array('coordinator', $currentUser->getRoles())) {
      $oldState = $node->field_state->value;
      $newState = AssessmentWorkflow::STATUS_UNDER_EVALUATION;
      $node->set('field_coordinator', ['target_id' => $currentUser->id()]);
      $workflowService->createRevision($node, $newState, $currentUser->id(), "{$oldState} ({$node->getRevisionId()}) => {$newState}", TRUE);
    }
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
   * Hide paragraphs actions based on user permissions and field settings.
   *
   * @param array $form
   * @param NodeInterface $siteAssessment
   *   The form.
   */
  public static function hideParagraphsActions(array &$form, NodeInterface $siteAssessment) {
    if (!$siteAssessment->isDefaultTranslation()) {
      return;
    }

    $state = $siteAssessment->get('field_state')->value ?: AssessmentWorkflow::STATUS_NEW;

    /** @var \Drupal\Core\Session\AccountProxy $currentUser */
    $currentUser = \Drupal::currentUser();
    /** @var \Drupal\Core\Entity\EntityFieldManager $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fieldDefinitions */
    $fieldDefinitions = $entityFieldManager->getFieldDefinitions('node', 'site_assessment');

    $actions = ['add more', 'edit', 'delete',];

    $readOnlyForm = \Drupal::routeMatch()->getRouteObject()->getOption('_read_only_form');
    foreach ($fieldDefinitions as $field => $fieldDefinition) {
      if (!$fieldDefinition instanceof ConfigEntityBase) {
        continue;
      }

      if (empty($form[$field]['widget'])) {
        continue;
      }

      $widget = &$form[$field]['widget'];
      $editableField = !empty($fieldDefinition->getThirdPartySetting('iucn_assessment', 'editable_workflow_states')[$state]);
      $cardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();

      $disabledActions = [];
      foreach ($actions as $action) {
        if (!$editableField || $readOnlyForm) {
          $disabledActions[] = $action;
          continue;
        }

        if ($cardinality == 1 and $action != 'edit') {
          continue;
        }

        $permission = "$action $field";
        if ($currentUser->hasPermission($permission)) {
          continue;
        }

        if (static::isPermissionException($field, $action, $currentUser->getRoles(TRUE), $siteAssessment)) {
          continue;
        }

        $disabledActions[] = $action;
      }

      if ($disabledActions) {
        static::hideParagraphsActionFromWidget($widget, $disabledActions);
      }
    }
  }

  /**
   * Hide all paragraphs actions from a widget.
   *
   * @param array $widget
   *   The widget.
   * @param array $actions
   *   The action to hide
   **/
  public static function hideParagraphsActionFromWidget(array &$widget, array $actions) {
    static::disableWidget($widget);

    foreach ($actions as $action) {
      if ($action == 'add more') {
        if (!empty($widget['add more'])) {
          $widget['add more']['#access'] = FALSE;
        }
        if (!empty($widget['add_more'])) {
          $widget['add_more']['#access'] = FALSE;
        }
      }

      foreach (Element::children($widget) as $child) {
        unset($widget[$child]['top']['actions']['buttons'][$action]);
      }
    }

    foreach (Element::children($widget) as $child) {
      if (empty($widget[$child]['top']['actions']['buttons'])) {

        unset($widget[$child]['top']['actions']);
        unset($widget[$child]['top']['summary']['actions']);
        continue;
      }

      foreach ($widget[$child]['top']['actions']['buttons'] as $button) {
        if (!is_array($button)) {
          continue;
        }

        if (!empty($button['#access'])) {
          continue 2;
        }
      }

      unset($widget[$child]['top']['actions']);
      unset($widget[$child]['top']['summary']['actions']);
    }
  }
  /**
   * Disable a widget.
   *
   * @param array $widget
   *   The widget.
   **/
  public static function disableWidget(array &$widget) {
    $disabledInputs = ['select', 'text_format', 'entity_autocomplete', 'managed_file'];

    if (!empty($widget['#type']) && in_array($widget['#type'], $disabledInputs)) {
      $widget['#disabled'] = true;
    }

    foreach (Element::children($widget) as $child) {
      if (!empty($widget[$child]['#type']) && in_array($widget[$child]['#type'], $disabledInputs)) {
        $widget[$child]['#disabled'] = true;
      }

      if (!empty($widget[$child]['target_id']['#type']) && in_array($widget[$child]['target_id']['#type'], $disabledInputs)) {
        $widget['#disabled'] = true;
      }
    }
  }

  public static function buildDiffButtons(&$form, $node) {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $diff = self::getNodeSettings($node, 'diff');
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

  public static function getNodeSettings($node, $settingsKey) {
    $settings = $node->field_settings->value;
    if (empty($settings)) {
      return [];
    }

    $settings = json_decode($settings, TRUE);
    if (empty($settings[$settingsKey])) {
      return [];
    }

    return $settings[$settingsKey];
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
        'field_wrapper_id' => get_wrapper_html_id($field),
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

  public static function changeTabLabel(&$tab) {
    if (in_array('reviewer', \Drupal::currentUser()->getRoles())) {
      if (!empty($tab['#link']['title'])) {
        $tab['#link']['title'] = t('Submit review');
      }
    }
  }

  public static function isPermissionException($field, $action, array $roles, NodeInterface $assessment) {
    switch ([$field, $action, TRUE]) {
      case ['field_as_values_wh', 'edit', in_array('assessor', $roles)];
      case ['field_as_values_wh', 'edit', in_array('reviewer', $roles)];
        if (\Drupal::request()->query->get('tab') == 'assessing-values') {
          return TRUE;
        }
      break;
    }

    // These fields are editable by assessors if the site has no previous assessment.
    if (in_array($field, ['field_as_values_wh', 'field_as_values_bio']) && in_array('assessor', $roles)) {
      $site = $assessment->field_as_site->target_id;
      if (empty($site)) {
        return FALSE;
      }

      $query = \Drupal::entityQuery('node')
        ->condition('type', 'site_assessment')
        ->condition('field_as_site', $site);
      $results = $query->execute();
      if (count($results) === 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  protected static function alterFieldsRestrictions($tab, array &$form, NodeInterface $node) {
    switch ($tab) {
      case 'benefits':
        if (!empty($node->get('field_as_benefits')->getValue())) {
          $form['field_as_benefits_summary']['widget'][0]['value']['#required'] = TRUE;
        }
        break;

      case 'assessing-values':
        $required_fields = [
          'field_as_vass_bio_text',
          'field_as_vass_bio_state',
          'field_as_vass_bio_trend',
        ];
        if (!empty($node->get('field_as_values_bio')->getValue())) {
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
        else {
          // Hide these fields if there are no other biodiversity values.
          foreach ($required_fields as $field) {
            $form[$field]['#access'] = FALSE;
          }
        }
        break;

      case 'threats':
        if ($node->get('field_as_threats_potential')->isEmpty()) {
          $form['field_as_threats_potent_text']['widget'][0]['#required'] = FALSE;
          $form['field_as_threats_potent_text']['widget'][0]['#wrapper_attributes']['class'][] = 'visually-hidden';
          $form['field_as_threats_potent_rating']['widget']['#required'] = FALSE;
          $form['field_as_threats_potent_rating']['widget']['#wrapper_attributes']['class'][] = 'visually-hidden';
        }
        break;
    }
  }

}
