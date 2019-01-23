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
  protected $entityFormBuilder;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\Core\Entity\ContentEntityStorageInterface */
  protected $paragraphStorage;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var \Drupal\node\NodeInterface|null */
  protected $nodeRevision;

  /** @var \Drupal\node\NodeInterface|null */
  protected $formNodeRevision;

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
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');
    $this->entityFormBuilder = $entityFormBuilder;
    $this->workflowService = $assessmentWorkflow;

    $routeMatch = $this->getRouteMatch();
    $this->field = $routeMatch->getParameter('field');
    $this->displayMode = $routeMatch->getParameter('display_mode');
    $this->paragraphRevision = $routeMatch->getParameter('paragraph_revision');
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->formNodeRevision = $this->workflowService->getPreviousWorkflowRevision($this->nodeRevision);

    foreach ($this->formNodeRevision->{$this->field}->getValue() as $value) {
      // Avoid loading all paragraphs for that field.
      if (!empty($value['target_id']) && $value['target_id'] == $this->paragraphRevision->id()) {
        $this->formNodeRevision->{$this->field}->setValue([0 => $value]);
        break;
      }
    }

    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
      // Remove all other fields except the selected one.
      if ($name != $this->field) {
        $this->nodeFormDisplay->removeComponent($name);
      }
    }
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
    $form = parent::buildForm($form, $form_state);
    // @todo remove the next 2 lines
    iucn_assessment_form_alter($form, $form_state, $this->getFormId());
    $form['#processed'] = TRUE;

    $settings = json_decode($this->nodeRevision->field_settings->value, TRUE);

    // Get the rendered field from the entity form.
    $nodeForm = $this->entityFormBuilder->getForm($this->formNodeRevision, 'default', [
      'form_display' => $this->nodeFormDisplay,
      'entity_form_initialized' => TRUE,
    ]);
    $nodeForm = $nodeForm[$this->field];
    // Remove unnecessary data from the table.
    NodeSiteAssessmentForm::hideParagraphsActionsFromWidget($nodeForm['widget'], FALSE);
    unset($nodeForm['widget']['#title']);
    unset($nodeForm['widget']['#description']);

    $nodeForm['widget']['#hide_draggable'] = TRUE;
    $paragraph_key = 0;

    // Add the author table cell.
    $this->addAuthorCell($nodeForm['widget']['header'], 'data', t('Author'), 'author', 2, -100);
    $this->addAuthorCell($nodeForm['widget'][$paragraph_key]['top'], 'summary', t('Initial version'), 'author', 2, -100);

    $initial_copy_value_buttons = [];
    $this->paragraphStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
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
      $row = $nodeForm['widget'][$paragraph_key];
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
        $paragraph = $this->paragraphStorage->loadRevision($data_value['target_revision_id']);
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
        $paragraph_0 = $this->paragraphStorage->loadRevision($data_value_0['target_revision_id']);
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

        $type = $this->getDiffFieldType($form[$diff_field]['widget']);
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
      $nodeForm['widget'][] = $row;
    }
    $nodeForm['widget']['#is_diff_form'] = TRUE;
    $nodeForm['widget']['edit'] = $nodeForm['widget'][$paragraph_key];

    $nodeForm['widget']['edit']['top']['summary']['author']['data']['#markup'] = '<b>' . t('Final version') . '</b>';
    $nodeForm['widget']['edit']['top']['#attributes']['class'][] = 'paragraph-diff-final';

    foreach (RowParagraphsWidget::getFieldComponents($this->paragraphRevision, $this->displayMode) as $this->field => $data) {
      $grouped_with = !empty($grouped_fields[$this->field]) ? $grouped_fields[$this->field]['grouped_with'] : $this->field;
      if (in_array($this->field, array_keys($form))) {
        if (($this->field == 'field_as_threats_values_bio' || $this->field == 'field_as_threats_values_wh')
          && empty($form[$this->field . '_select_wrapper']['#printed'])) {
          unset($form[$this->field . '_select_wrapper'][$this->field . '_select']['#title']);
          $nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field] = $form[$this->field . '_select_wrapper'][$this->field . '_select'];
          $nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field]['#parents'] = [$this->field . '_select'];
          unset($nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          unset($form[$this->field . '_select_wrapper']);
        }
        else {
          if (!empty($form[$this->field]['widget']['#title'])) {
            $form[$this->field]['widget']['#title_display'] = 'invisible';
          }
          if (!empty($form[$this->field]['widget'][0]['value']['#title'])) {
            $form[$this->field]['widget'][0]['value']['#title_display'] = 'invisible';
          }
          unset($nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data']['#markup']);
          $nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field] = $form[$this->field];
          if ($this->field != $grouped_with) {
            $nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data'][$this->field]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($this->field) . '</b>';
            $nodeForm['widget']['edit']['top']['summary'][$grouped_with]['data'][$grouped_with]['#prefix'] =
              '<b>' . RowParagraphsWidget::getSummaryPrefix($grouped_with) . '</b>';
          }
        }
        unset($form[$this->field]);
      }
    }

    unset($nodeForm['widget']['#element_validate']);

    $nodeForm['widget'][$paragraph_key]['#attributes']['class'][] = 'diff-original-row';
    foreach ($initial_copy_value_buttons as $grouped_with => $button) {
      $data = $nodeForm['widget'][$paragraph_key]['top']['summary'][$grouped_with];
      $nodeForm['widget'][$paragraph_key]['top']['summary'][$grouped_with] = [
        "#type" => $data['#type'],
        "#attributes" => $data['#attributes'],
        "#id" => $data['#id'],
      ];
      unset($data['#type']);
      unset($data['#attributes']);
      unset($data['#id']);
      $nodeForm['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data'] = $data;
      $nodeForm['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data']['#prefix'] = '<div class="diff-wrapper">';
      $nodeForm['widget'][$paragraph_key]['top']['summary'][$grouped_with]['data']['#suffix'] = $button . '</div>';
    }

    $form['diff'] = $nodeForm;
    $form['diff']['#weight'] = 0;
    unset($form['#fieldgroups']);

    return $form;
  }

}
