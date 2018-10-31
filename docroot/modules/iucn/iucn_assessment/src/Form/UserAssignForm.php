<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;

/**
 * Bulk assign a single user to multiple assessments.
 */
class UserAssignForm extends FormBase {

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\iucn_assessment\Plugin\AssessmentWorkflow definition.
   *
   * @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow
   */
  protected $iucnAssessmentWorkflow;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  /**
   * Constructs a new UserAssignForm object.
   */
  public function __construct(AccountProxyInterface $currentUser, AssessmentWorkflow $iucnAssessmentWorkflow, EntityTypeManagerInterface $entityTypeManager) {
    $this->currentUser = $currentUser;
    $this->iucnAssessmentWorkflow = $iucnAssessmentWorkflow;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('iucn_assessment.workflow'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultAllowed
   */
  public function access(AccountInterface $account) {
    // @todo
    return AccessResult::allowed();
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

  public function afterBuild($form, FormStateInterface $form_state) {
    $form['assessments']['#options'] = $this->getAvailableAssessments($form_state->getValue('role'));
    return $form;
  }

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
      $assessment->save();
      $context['results']['count']++;
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not save assessment %ass', ['%ass' => $assessment->toLink()->toString()]));
    }
  }

  public function finishProcessingAssessments($success, $results, $operations) {
    if ($success) {
      $this->messenger()->addStatus($this->t('Successfully assigned user to %num assessments.', ['%num' => $results['count']]));
    }
    else {
      $this->messenger()->addError($this->t('The batch processing failed'));
    }
  }

  protected function getAvailableAssessments($role) {
    if (empty($role)) {
      return [];
    }

    $states = [
      'assessment_creation',
      'assessment_new',
      'assessment_under_evaluation',
      'assessment_under_assessment',
      'assessment_ready_for_review',
    ];
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'site_assessment')
      ->condition('field_state', $states, 'IN')
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

  protected function getFieldName($role) {
    return ($role == 'reviewer') ? "field_{$role}s" : "field_{$role}";
  }

}
