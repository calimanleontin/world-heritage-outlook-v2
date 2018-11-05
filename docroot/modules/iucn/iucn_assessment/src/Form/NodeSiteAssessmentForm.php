<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\user\Entity\User;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

  public static function alter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow $workflow_service */
    $workflow_service = \Drupal::service('iucn_assessment.workflow');
    $tab = \Drupal::request()->get('tab') ?: 'values';
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
      if (self::isValuesTab()) {
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
          dpm($settings);
          $form["comment_$tab"]['#attributes'] = ['readonly' => 'readonly'];
          unset($form["comment_$tab"]['#description']);
          $comments = '';
          if (!empty($settings['comments'][$tab])) {
            foreach ($settings['comments'][$tab] as $uid => $comment) {
              $comments .= '<b>' . User::load($uid)->getDisplayName() . ':</b> ' . $comment . "\n\n";
            }
          }
          $form["comment_$tab"]['#type'] = 'markup';
          $form["comment_$tab"]['#markup'] = $comments;
        }
        $form['#attached']['library'][] = 'iucn_assessment/paragraph_comments';
        $form['#attached']['library'][] = 'iucn_backend/font-awesome';
      }
    }

    array_unshift($form['actions']['submit']['#submit'], [self::class, 'setAssessmentSettings']);
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
      if (preg_match('/^comment\_(.+)$/', $key, $matches)) {
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
    if ($workflow_service->checkAssessmentAccess($node)->isAllowed()) {
      if ($workflow_service->isAssessmentEditable($node)) {
        $form_state->setRedirectUrl($node->toUrl('edit-form', $options));
      }
      else {
        $form_state->setRedirect('iucn_assessment.node.state_change', ['node' => $node->id()]);
      }
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
      $form[$field]['widget']['add_more']['#access'] = FALSE;
      $paragraphs = &$form[$field]['widget'];
      if (!empty($paragraphs['header']['data']['actions'])) {
        $paragraphs['header']['data']['actions']['#access'] = FALSE;
        $classes = &$paragraphs['header']['#attributes']['class'];
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
      foreach ($paragraphs as $key => &$paragraph) {
        if (!is_int($key)) {
          continue;
        }
        $paragraph['top']['actions']['#access'] = FALSE;
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

  /**
   * Check if we are on the values tab.
   */
  public static function isValuesTab() {
    $tab = \Drupal::request()->query->get('tab');
    return empty($tab) || $tab == 'values';
  }

}
