<?php

namespace Drupal\iucn_notifications\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NotificationSettingsForm extends FormBase {

  /** @var \Drupal\Core\State\StateInterface */
  protected $state;

  /** @var \Drupal\Core\Messenger\MessengerInterface */
  protected $messenger;

  /**
   * NotificationSettingsForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(StateInterface $state, MessengerInterface $messenger) {
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('messenger')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'iucn_notifications.settings';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['iucn_notifications.archive_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Archive email'),
      '#default_value' => $this->state->get('iucn_notifications.archive_email'),
    ];

    $form['iucn_notifications.coordinator_extra_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Extra coordinator email'),
      '#default_value' => $this->state->get(
        'iucn_notifications.coordinator_extra_email'
      ),
      '#description' => $this->t(
        'All email notifications that are sent to coordinators will be also sent to this email'
      ),
    ];


    $emailsToReviewers = $this->state->get(
      'iucn_notifications.review_completion_emails'
    );
    $form['iucn_notifications.review_completion_emails_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notify review completion'),
      '#description' => $this->t(
        'All email notifications that are sent to coordinators noting review completion will be also sent to this emails'
      ),
      'iucn_notifications.review_completion_emails' => [
        [
          '#type' => 'email',
          '#default_value' => !empty($emailsToReviewers[0]) ? $emailsToReviewers[0] : NULL,
          '#name' => 'iucn_notifications.review_completion_emails[]',
        ],
        [
          '#type' => 'email',
          '#default_value' => !empty($emailsToReviewers[1]) ? $emailsToReviewers[1] : NULL,
          '#name' => 'iucn_notifications.review_completion_emails[]',
        ],
        [
          '#type' => 'email',
          '#default_value' => !empty($emailsToReviewers[2]) ? $emailsToReviewers[2] : NULL,
          '#name' => 'iucn_notifications.review_completion_emails[]',
        ],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();
    $this->state->set(
      'iucn_notifications.archive_email',
      $userInput['iucn_notifications_archive_email']
    );
    $this->state->set(
      'iucn_notifications.coordinator_extra_email',
      $userInput['iucn_notifications_coordinator_extra_email']
    );
    $this->state->set(
      'iucn_notifications.review_completion_emails',
      array_filter($userInput['iucn_notifications_review_completion_emails'])
    );

    $this->messenger->addMessage($this->t('Settings saved successfully!'));
  }

}
