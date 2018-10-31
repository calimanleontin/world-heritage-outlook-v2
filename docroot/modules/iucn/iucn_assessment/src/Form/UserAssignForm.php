<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Access\AccessResult;
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

  /**
   * Constructs a new UserAssignForm object.
   */
  public function __construct(AccountProxyInterface $currentUser, AssessmentWorkflow $iucnAssessmentWorkflow) {
    $this->currentUser = $currentUser;
    $this->iucnAssessmentWorkflow = $iucnAssessmentWorkflow;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('iucn_assessment.workflow')
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
    $roles = array_combine($roles, $roles);
    $form = [
      '#title' => t('Assign %user to multiple sites', ['%user' => $user->getAccountName()]),
    ];
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#multiple' => FALSE,
      '#options' => $roles,
      '#weight' => 0,
    ];
    $form['sites'] = [
      '#type' => 'select',
      '#title' => $this->t('Sites'),
      '#multiple' => TRUE,
      '#options' => [],
      '#weight' => 1,
    ];
    $form['assign'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assign'),
      '#weight' => 2,
    ];
    return $form;
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
