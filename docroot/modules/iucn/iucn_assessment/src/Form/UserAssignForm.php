<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;

/**
 * Class UserAssignForm.
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
    if (!$user instanceof UserInterface || empty($roles = array_intersect(['coordinator', 'assessor', 'reviewer'], $user->getRoles()))) {
      $this->messenger()->addError('This user cannot be assigned to any site.');
      return;
    }
    $roles = [NULL => t('- Select -')] + array_combine($roles, $roles);
    $form = [
      '#title' => t('Assign %user to multiple sites', ['%user' => $user->getAccountName()]),
      'role' => [
        '#type' => 'select',
        '#title' => $this->t('Role'),
        '#multiple' => FALSE,
        '#required' => TRUE,
        '#options' => $roles,
        '#ajax' => [
          'callback' => '::roleAjaxCallback',
          'wrapper' => 'sites-container',
        ],

      ],
      'sites' => [
        '#type' => 'select',
        '#title' => $this->t('Sites'),
        '#multiple' => TRUE,
        '#required' => TRUE,
        '#options' => [],
        '#chosen' => TRUE,
        '#prefix' => '<div id="sites-container">',
        '#suffix' => '</div>',
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Assign'),
      ],
    ];
    return $form;
  }

  public function roleAjaxCallback(array $form, FormStateInterface $form_state) {
    $role = $form_state->getValue('role');
    if (empty($role)) {
      $form['sites']['#options'] = [];
      return $form['sites'];
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
      ->notExists(($role == 'reviewer') ? "field_{$role}s" : "field_{$role}");
    $ids = $query->execute();
    if (empty($ids)) {
      $this->messenger()->addError(t('There are no assessments to which the user can be assigned.'));
      return $form['sites'];
    }

    /** @var \Drupal\node\NodeInterface[] $assessments */
    $assessments = $this->nodeStorage->loadMultiple($ids);
    $options = [];
    foreach ($assessments as $assessment) {
      $options[$assessment->id()] = $assessment->getTitle();
    }
    $form['sites']['#options'] = $options;
    return $form['sites'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo BATCH!
  }

}
