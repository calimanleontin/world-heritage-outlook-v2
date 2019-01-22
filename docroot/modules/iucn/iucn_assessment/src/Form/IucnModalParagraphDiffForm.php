<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\iucn_assessment\Plugin\Field\FieldWidget\RowParagraphsWidget;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalParagraphDiffForm extends IucnModalDiffForm {

  /** @var \Drupal\Core\Entity\EntityFormBuilderInterface */
  protected $formBuilder;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\node\NodeInterface|null */
  protected $nodeRevision;

  /** @var \Drupal\paragraphs\ParagraphInterface */
  protected $paragraphRevision;

  /** @var string|null  */
  protected $field;

  /** @var string|null  */
  protected $displayMode;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null  */
  protected $nodeFormDisplay;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, EntityFormBuilderInterface $entityFormBuilder = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');
    $this->formBuilder = $entityFormBuilder;
    $this->workflowService = $assessmentWorkflow;

    $routeMatch = $this->getRouteMatch();
    $this->field = $routeMatch->getParameter('field');
    $this->displayMode = $routeMatch->getParameter('display_mode');
    $this->paragraphRevision = $routeMatch->getParameter('paragraph_revision');
    $this->nodeRevision = $routeMatch->getParameter('node_revision');

//    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
//    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
//      // Remove all other fields except the selected one.
//      if ($name != $this->field) {
//        $this->nodeFormDisplay->removeComponent($name);
//      }
//    }
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('iucn_assessment.workflow')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $paragraph_form = parent::buildForm($form, $form_state);
    iucn_assessment_form_alter($paragraph_form, $form_state, self::getFormId());
    $paragraph_form['#processed'] = TRUE;

    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);
    if (!empty($settings['diff']) && ($firstKey = key($settings['diff'])) && !empty($settings['diff'][$firstKey]['node'][$this->nodeRevision->id()]['initial_revision_id'])) {
      $form_revision = $this->workflowService->getAssessmentRevision($settings['diff'][$firstKey]['node'][$this->nodeRevision->id()]['initial_revision_id']);
    }
    elseif ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
      $form_revision = $this->workflowService->getRevisionByState($this->nodeRevision, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
    }
    else {
      $form_revision = $this->nodeRevision;
    }

    foreach ($form_revision->{$this->field}->getValue() as $value) {
      if (!empty($value['target_id']) && $value['target_id'] == $this->paragraphRevision->id()) {
        $form_revision->{$this->field}->setValue([0 => $value]);
        break;
      }
    }

    // Get the rendered field from the entity form.
    $form = $this->formBuilder->getForm($form_revision, 'default')[$this->field];
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
      if (empty($diff['paragraph'][$this->paragraphRevision->id()])) {
        continue;
      }
      $diff = $diff['paragraph'][$this->paragraphRevision->id()]['diff'];
      /** @var NodeInterface $assessment_revision */
      $assessment_revision = $this->workflowService->getAssessmentRevision($assessment_vid);

      if ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
        $author = $this->nodeRevision->field_assessor->entity->getDisplayName();
      }
      else {
        $author = User::load($assessment_revision->getRevisionUserId())->getDisplayName();
      }

      // Copy the initial row.
      $row = $form['widget'][$paragraph_key];
      $diff_fields = array_keys($diff);

      // If the row is actually deleted, only apply a different class.
      $deleted = FALSE;
      if (!in_array($this->paragraphRevision->id(), array_column($assessment_revision->get($this->field)->getValue(), 'target_id'))) {
        $row['top']['#attributes']['class'][] = 'paragraph-deleted-row';
        $deleted = TRUE;
      }

      $grouped_fields = RowParagraphsWidget::getGroupedFields();
      $grouped_with_fields = [];
      foreach ($grouped_fields as $grouped_field => $group_settings) {
        $grouped_with = $group_settings['grouped_with'];

        $grouped_with_fields[] = $grouped_with;
        if ($this->paragraphRevision->hasField($grouped_field)) {
          $value1 = $this->paragraphRevision->get($grouped_field)->view(['settings' => ['link' => 0]]);
          $value1['#title'] = RowParagraphsWidget::getSummaryPrefix($grouped_field);
        }

        if ($this->paragraphRevision->hasField($grouped_with)) {
          $value2 = $this->paragraphRevision->get($grouped_with)->view(['settings' => ['link' => 0]]);
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
          $initial_revision = $this->workflowService->getAssessmentRevision($settings['diff'][$assessment_revision->getRevisionId()]['node'][$assessment_revision->id()]['initial_revision_id']);
          $data_value_0  = $initial_revision->get($this->field)->getValue()[$paragraph_key];
          $data_value= $assessment_revision->get($this->field)->getValue()[$paragraph_key];
        }
        elseif ($this->nodeRevision->field_state->value == AssessmentWorkflow::STATUS_READY_FOR_REVIEW) {
          $data_value_0 = $assessment_revision->get($this->field)->getValue()[$paragraph_key];
          $data_value = $this->nodeRevision->get($this->field)->getValue()[$paragraph_key];
        }
        else {
          $data_value_0 = $this->nodeRevision->get($this->field)->getValue()[$paragraph_key];
          $data_value = $assessment_revision->get($this->field)->getValue()[$paragraph_key];
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
        $copy_value_button = $this->getCopyValueButton($assessment_vid, $type, $diff_field, $data_value, $grouped_with);
        $init_button = $this->getCopyValueButton(0, $type, $diff_field, $data_value_0, $grouped_with);
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
    $form['widget']['#is_diff_form'] = TRUE;
    $form['widget']['edit'] = $form['widget'][$paragraph_key];

    $form['widget']['edit']['top']['summary']['author']['data']['#markup'] = '<b>' . t('Final version') . '</b>';
    $form['widget']['edit']['top']['#attributes']['class'][] = 'paragraph-diff-final';

    foreach (RowParagraphsWidget::getFieldComponents($this->paragraphRevision, $this->displayMode) as $this->field => $data) {
      $grouped_with = !empty($grouped_fields[$this->field]) ? $grouped_fields[$this->field]['grouped_with'] : $this->field;
      if (in_array($this->field, array_keys($paragraph_form))) {
        if (($this->field == 'field_as_threats_values_bio' || $this->field == 'field_as_threats_values_wh')
          && empty($paragraph_form[$this->field . '_select_wrapper']['#printed'])) {
          unset($paragraph_form[$this->field . '_select_wrapper'][$this->field . '_select']['#title']);
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field] = $paragraph_form[$this->field . '_select_wrapper'][$this->field . '_select'];
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field]['#parents'] = [$this->field . '_select'];
          unset($form['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          unset($paragraph_form[$this->field . '_select_wrapper']);
        }
        else {
          if (!empty($paragraph_form[$this->field]['widget']['#title'])) {
            $paragraph_form[$this->field]['widget']['#title_display'] = 'invisible';
          }
          if (!empty($paragraph_form[$this->field]['widget'][0]['value']['#title'])) {
            $paragraph_form[$this->field]['widget'][0]['value']['#title_display'] = 'invisible';
          }
          unset($form['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field] = $paragraph_form[$this->field];
          if ($this->field != $grouped_with) {
            $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($this->field) . '</b>';
            $form['widget']['edit']['top']['summary'][$grouped_with]['data'][$grouped_with]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($grouped_with) . '</b>';
          }
        }
        unset($paragraph_form[$this->field]);
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

    return $paragraph_form;
  }

}
