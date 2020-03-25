<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
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
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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

  /** @var string */
  protected $currentLanguage;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFormBuilderInterface $entity_form_builder = NULL, EntityTypeManagerInterface $entityTypeManager = NULL, PrivateTempStoreFactory $temp_store_factory = NULL, AssessmentWorkflow $assessmentWorkflow = NULL, LanguageManagerInterface $languageManager, RequestStack $requestStack) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setEntityTypeManager($entityTypeManager);
    $this->entityFormBuilder = $entity_form_builder;
    $this->workflowService = $assessmentWorkflow;
    $this->entityFormDisplay = $this->entityTypeManager->getStorage('entity_form_display');

    $routeMatch = $this->getRouteMatch();
    $this->nodeRevision = $routeMatch->getParameter('node_revision');
    $this->currentLanguage = $requestStack->getCurrentRequest()->query->get('language') ?: $this->nodeRevision->language()->getId();
    $this->paragraphRevision = $routeMatch->getParameter('paragraph_revision');

    if (!empty($this->paragraphRevision)) {
      if ($this->paragraphRevision->hasTranslation($this->currentLanguage)) {
        $this->paragraphRevision = $this->paragraphRevision->getTranslation($this->currentLanguage);
      }
      else {
        $translation = $this->paragraphRevision->addTranslation($this->currentLanguage, $this->paragraphRevision->toArray());
        $translation->save();
        $this->paragraphRevision = $translation;
      }
    }

    $this->fieldName = $routeMatch->getParameter('field');
    $this->fieldWrapperId = $routeMatch->getParameter('field_wrapper_id');
    $this->formDisplayMode = $routeMatch->getParameter('form_display_mode');

    $this->nodeFormDisplay = $this->entityFormDisplay->load("{$this->nodeRevision->getEntityTypeId()}.{$this->nodeRevision->bundle()}.default");
    foreach ($this->nodeFormDisplay->getComponents() as $name => $component) {
      // Remove all other fields except the selected one.
      if (!in_array($name, array_merge([$this->fieldName], $this->getDependentFields()))) {
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
      $container->get('iucn_assessment.workflow'),
      $container->get('language_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->set('langcode', $this->currentLanguage);
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
      return $response;
    }

    // Update parent node change date.
    $this->nodeRevision->setChangedTime(time());


    if (!empty(NodeSiteAssessmentForm::DEPENDENT_FIELDS[$this->fieldName]) && $this->nodeRevision->get($this->fieldName)->isEmpty()) {
       // If all potential threats paragraphs are deleted, the potential threats
       // text and rating fields are deleted.
      foreach (NodeSiteAssessmentForm::DEPENDENT_FIELDS[$this->fieldName] as $dependentField) {
        $this->nodeRevision->get($dependentField)->setValue(NULL);
      }
    }

    if ($this->nodeRevision->isDefaultRevision()
      && $this->workflowService->isNewAssessment($this->nodeRevision)
      && empty($this->nodeRevision->field_coordinator->target_id)
      && in_array('coordinator', $this->currentUser()->getRoles())) {
      // Sets the current user as a coordinator if he has the coordinator role
      // and edits the assessment.
      $oldState = $this->nodeRevision->field_state->value;
      $newState = AssessmentWorkflow::STATUS_UNDER_EVALUATION;

      $this->nodeRevision->set('field_coordinator', ['target_id' => $this->currentUser()->id()]);
      $new_revision = $this->workflowService->createRevision($this->nodeRevision, $newState, $this->currentUser()->id(), "{$oldState} ({$this->nodeRevision->getRevisionId()}) => {$newState}", TRUE);

      $response->addCommand(
        new ReplaceCommand(
          "#node-site-assessment-edit-form .current-state",
          NodeSiteAssessmentForm::getCurrentStateMarkup($new_revision)
        )
      );
    }
    else {
      $this->nodeRevision->save();
    }

    $tab = \Drupal::request()->get('tab');
    if ($tab == 'assessing-values') {
      $content = $this->nodeFormDisplay->getComponents();
      $content['field_as_values_wh']['settings']['form_display_mode'] = 'assessing_values';
      $content['field_as_values_wh']['settings']['only_editable'] = true;
      $this->nodeFormDisplay->setComponent('field_as_values_wh', $content['field_as_values_wh']);
    }

    $translation = $this->nodeRevision->hasTranslation($this->currentLanguage)
      ? $this->nodeRevision->getTranslation($this->currentLanguage)
      : $this->nodeRevision->addTranslation($this->currentLanguage);

    $nodeForm = $this->entityFormBuilder->getForm($translation, 'default', [
      'form_display' => $this->nodeFormDisplay,
      'entity_form_initialized' => TRUE,
      'langcode' => $this->currentLanguage,
    ]);

    // Refresh the paragraphs field.
    $response->addCommand(
      new ReplaceCommand(
        "{$this->fieldWrapperId} >:first-child",
        $nodeForm[$this->fieldName]['widget']
      )
    );

    foreach ($this->getDependentFields() as $dependentField) {
      $wrapper = get_wrapper_html_id($dependentField);
      $response->addCommand(
        new ReplaceCommand(
          "{$wrapper} >:first-child",
          $nodeForm[$dependentField]['widget']
        )
      );
    }

    $response->addCommand(new CloseModalDialogCommand());

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

  protected function getDependentFields() {
    if (empty($this->fieldName)) {
      return [];
    }

    if (empty(NodeSiteAssessmentForm::DEPENDENT_FIELDS[$this->fieldName])) {
      return [];
    }

    return NodeSiteAssessmentForm::DEPENDENT_FIELDS[$this->fieldName];
  }

}
