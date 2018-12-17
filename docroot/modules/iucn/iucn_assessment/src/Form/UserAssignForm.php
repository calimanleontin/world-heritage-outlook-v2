<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bulk assign a single user to multiple assessments.
 */
class UserAssignForm extends FormBase {

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /** @var \Drupal\Core\State\StateInterface */
  protected $state;

  /** @var int */
  protected $currentWorkflowCycle;

  /**
   * Constructs a new UserAssignForm object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, StateInterface $state) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->state = $state;
    $this->currentWorkflowCycle = $this->state->get(AssessmentWorkflow::CURRENT_WORKFLOW_CYCLE_STATE_KEY, 2020);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_assign_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    if (!$user instanceof UserInterface || empty($roles = array_intersect([
        'coordinator',
        'assessor',
        'reviewer',
      ], $user->getRoles()))) {
      $this->messenger()->addError('This user cannot be assigned to any site.');
      return $form;
    }
    $roles = [NULL => $this->t('- Select -')] + array_combine($roles, $roles);
    $form['#title'] = $this->t('Assign %user to multiple assessments', ['%user' => $user->getAccountName()]);
    $form['user'] = [
      '#type' => 'value',
      '#value' => $user,
    ];
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#options' => $roles,
      '#ajax' => [
        'callback' => '::roleAjaxCallback',
        'wrapper' => 'assessments-container',
      ],
    ];
    if (count($roles) == 2) {
      end($roles);
      $form['role']['#value'] = key($roles);
    }
    $form['assessments'] = [
      '#type' => 'select',
      '#title' => $this->t('Assessments'),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#options' => [],
      '#chosen' => TRUE,
      '#prefix' => '<div id="assessments-container">',
      '#suffix' => '</div>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assign'),
    ];
    $form['#after_build'][] = [$this, 'afterBuild'];
    return $form;
  }

  /**
   * Provide assessments select options.
   */
  public function afterBuild($form, FormStateInterface $form_state) {
    $form['assessments']['#options'] = $this->getAvailableAssessments($form_state->getValue('role'));
    return $form;
  }

  /**
   * Refresh the assessments select element.
   */
  public function roleAjaxCallback(array $form, FormStateInterface $form_state) {
    return $form['assessments'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $form_state->getValue('user');
    $role = $form_state->getValue('role');
    $assessments = $form_state->getValue('assessments');
    $operations = [];
    foreach ($assessments as $assessment) {
      $operations[] = [
        [$this, 'processAssessment'],
        [
          'user' => $user,
          'role' => $role,
          'assessmentId' => $assessment,
        ],
      ];
    }
    $batch = [
      'title' => $this->t('Processing assessments...'),
      'operations' => $operations,
      'finished' => [$this, 'finishProcessingAssessments'],
    ];
    batch_set($batch);
  }

  /**
   * Assign the user to assessment.
   */
  public function processAssessment(UserInterface $user, $role, $assessmentId, &$context) {
    if (empty($context['results'])) {
      $context['results']['count'] = 0;
    }
    $assessment = Node::load($assessmentId);
    $fieldName = $this->getFieldName($role);
    try {
      if (!empty($assessment->{$fieldName}->getValue())) {
        throw new \Exception('Field is not empty');
      }
      $assessment->set($fieldName, $user->id());
      $state = $assessment->field_state->value;
      if (in_array($state, [
          AssessmentWorkflow::STATUS_CREATION,
          AssessmentWorkflow::STATUS_NEW,
        ]) && !empty($assessment->field_coordinator->getValue())) {
        // If the coordinator was set, set assessment status to UNDER EVALUATION.
        \Drupal::service('iucn_assessment.workflow')->forceAssessmentState($assessment, AssessmentWorkflow::STATUS_UNDER_EVALUATION);
      }
      $assessment->save();
      $context['results']['count']++;
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not save assessment %ass', ['%ass' => $assessment->toLink()->toString()]));
    }
  }

  /**
   * The batch process finished.
   */
  public function finishProcessingAssessments($success, $results, $operations) {
    if ($success) {
      $this->messenger()->addStatus($this->t('Successfully assigned user to %num assessments.', ['%num' => $results['count']]));
    }
    else {
      $this->messenger()->addError($this->t('The batch processing failed'));
    }
  }

  /**
   * Retrieve available assessments for a specific role.
   */
  protected function getAvailableAssessments($role) {
    if (empty($role)) {
      return [];
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'site_assessment')
      ->condition('field_state', AssessmentWorkflow::USER_ASSIGNMENT_STATES, 'IN')
      ->condition('field_as_cycle', $this->currentWorkflowCycle)
      ->notExists($this->getFieldName($role));
    $ids = $query->execute();
    if (empty($ids)) {
      $this->messenger()
        ->addError($this->t('There are no assessments to which the user can be assigned.'));
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $assessments */
    $assessments = $this->nodeStorage->loadMultiple($ids);
    $options = [];
    foreach ($assessments as $assessment) {
      $options[$assessment->id()] = $assessment->getTitle();
    }
    return $options;
  }

  /**
   * Calculate name of the field based on user role (field_coordinator,
   * field_assessor OR field_reviewers).
   */
  protected function getFieldName($role) {
    return ($role == 'reviewer') ? "field_{$role}s" : "field_{$role}";
  }
}
