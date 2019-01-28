<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalParagraphForm extends ContentEntityForm {

  use AssessmentEntityFormTrait;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeRevision;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var string
   */
  protected $fieldWrapperId;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityFormBuilder = $entity_form_builder;

    $routeMatch = $this->getRouteMatch();
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->fieldName = $routeMatch->getParameter('field');
    $this->fieldWrapperId = $routeMatch->getParameter('field_wrapper_id');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#prefix'] = '<div id="drupal-modal">';
    $form['#suffix'] = '</div>';

    // @TODO: fix problem with form is outdated.
    $form['#token'] = FALSE;

    // Define alternative submit callbacks using AJAX by copying the default
    // submit callbacks to the AJAX property.
    $form['actions']['submit']['#ajax'] = [
      'callback' => '::ajaxSave',
      'event' => 'click',
      'progress' => [
        'type' => 'throbber',
        'message' => NULL,
      ],
      'disable-refocus' => TRUE,
    ];

    $this->buildCancelButton($form);
    $this->hideUnnecessaryFields($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSave(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // When errors occur during form validation, show them to the user.
    if ($form_state->getErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#drupal-modal', $form));
    }
    else {
      // Update parent node change date.
      $this->nodeRevision->setChangedTime(time());
      $this->nodeRevision->save();

      // Get all necessary data to be able to correctly update the correct
      // field on the parent node.
      $temporary_data = $form_state->getTemporary();
      $parent_entity_revision = isset($temporary_data['node_revision']) ?
        $temporary_data['node_revision'] :
        $this->nodeRevision;
      $parent_entity_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($parent_entity_revision->getRevisionId());

      // Refresh the paragraphs field.
      $response->addCommand(
        new HtmlCommand(
          $this->fieldWrapperId,
          $this->entityFormBuilder->getForm($parent_entity_revision, 'default')[$this->fieldName]
        )
      );

      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

  /**
   * Build the cancel button.
   *
   * @param $form
   */
  public function buildCancelButton(&$form) {
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'modal-cancel-button',
        ],
      ],
      '#ajax' => [
        'callback' => '::closeModalForm',
        'event' => 'click',
      ],
      '#limit_validation_errors' => [],
      '#submit' => [],
      '#weight' => 10,
    ];
  }

  /**
   * Ajax callback for the cancel button.
   */
  public function closeModalForm() {
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

}
