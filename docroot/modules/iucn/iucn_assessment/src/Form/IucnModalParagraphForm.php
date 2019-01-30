<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IucnModalParagraphForm extends ContentEntityForm {

  use AssessmentEntityFormTrait;

  /** @var \Drupal\Core\Form\FormBuilderInterface */
  protected $entityFormBuilder;

  /** @var \Drupal\node\NodeInterface */
  protected $nodeRevision;

  /** @var \Drupal\paragraphs\ParagraphInterface */
  protected $paragraphRevision;

  /** @var string */
  protected $fieldName;

  /** @var string */
  protected $fieldWrapperId;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface */
  protected $entityFormDisplay;

  /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null  */
  protected $nodeFormDisplay;

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $workflowService;

  /** @var string */
  protected $formDisplayMode;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, PrivateTempStoreFactory $temp_store_factory = NULL, AssessmentWorkflow $assessmentWorkflow = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->entityFormBuilder = $entity_form_builder;
    $this->workflowService = $assessmentWorkflow;
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');

    $routeMatch = $this->getRouteMatch();
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->paragraphRevision = $routeMatch->getParameter('paragraph_revision');
    $this->fieldName = $routeMatch->getParameter('field');
    $this->fieldWrapperId = $routeMatch->getParameter('field_wrapper_id');
    $this->formDisplayMode = $routeMatch->getParameter('form_display_mode');

    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
      // Remove all other fields except the selected one.
      if ($name != $this->fieldName) {
        $this->nodeFormDisplay->removeComponent($name);
      }
    }
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('iucn_assessment.workflow')
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
      // Get all necessary data to be able to correctly update the correct
      // field on the parent node.
      $temporary_data = $form_state->getTemporary();
      $parent_entity_revision = isset($temporary_data['node_revision']) ?
        $temporary_data['node_revision'] :
        $this->nodeRevision;
      $parent_entity_revision = $this->workflowService->getAssessmentRevision($parent_entity_revision->getRevisionId());

      // Update parent node change date.
      $parent_entity_revision->setChangedTime(time());
      $parent_entity_revision->save();

      $nodeForm = $this->entityFormBuilder->getForm($parent_entity_revision, 'default', [
        'form_display' => $this->nodeFormDisplay,
        'entity_form_initialized' => TRUE,
      ]);

      // Refresh the paragraphs field.
      $response->addCommand(
        new ReplaceCommand(
          "{$this->fieldWrapperId} .js-form-item",
          $nodeForm[$this->fieldName]['widget']
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
