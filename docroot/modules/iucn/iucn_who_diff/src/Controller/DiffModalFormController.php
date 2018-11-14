<?php

namespace Drupal\iucn_who_diff\Controller;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iucn_assessment\Form\NodeSiteAssessmentForm;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;

/**
 * DiffModalFormController class.
 */
class DiffModalFormController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The DiffModalFormController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Callback for opening the modal form.
   */
  public function openModalForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();

    $field_name = $triggering_element['#field_name'];
    $paragraph_id = $triggering_element['#paragraph_id'];
    $paragraph_vid = $triggering_element['#paragraph_vid'];


    /** @var NodeInterface $assessment */
    $assessment = $form_state->getFormObject()->getEntity();

    // Get the rendered field from the entity form.
    $form = \Drupal::service('entity.form_builder')->getForm($assessment, 'default')[$field_name];
    // Remove unnecessary data from the table.
    NodeSiteAssessmentForm::hideParagraphsActionsFromWidget($form['widget'], FALSE);
    unset($form['widget']['#title']);
    unset($form['widget']['#description']);

    $form['widget']['#hide_draggable'] = TRUE;
    $paragraph_key = 0;
    foreach ($form['widget'] as $key => &$item) {
      if (!is_int($key)) {
        continue;
      }
      if ($item['#paragraph_id'] != $paragraph_id) {
        unset($form['widget'][$key]);
      }
      else {
        $paragraph_key = $key;
      }
    }

    // Add the author table cell.
    $author = $assessment->field_coordinator->entity->getDisplayName();
    $author_header = self::getTableCellMarkup(t('Author'), 'author');
    $author_container = self::getTableCellMarkup($author, 'author');
    $form['widget'][$paragraph_key]['top']['summary'] = ['author' => $author_container] + $form['widget'][$paragraph_key]['top']['summary'];
    $form['widget']['header']['data'] = ['author' => $author_header] + $form['widget']['header']['data'];

    $settings = json_decode($assessment->field_settings->value, TRUE);
    $diff = $settings['diff'];
    foreach ($settings['diff'] as $assessment_vid => $diff) {
      // For each revision that changed this paragraph.
      if (empty($diff[$paragraph_id])) {
        continue;
      }
      /** @var NodeInterface $assessment_revision */
      $assessment_revision = \Drupal::service('iucn_assessment.workflow')->getAssessmentRevision($assessment_vid);
      $author = User::load($assessment_revision->getRevisionUserId())->getDisplayName();

      // Copy the initial row.
      $row = $form['widget'][$paragraph_key];
      $diff_fields = array_keys($diff[$paragraph_id]['diff']);

      // Alter fields that have differences.
      foreach ($diff_fields as $diff_field) {
        if (empty($row['top']['summary'][$diff_field]['data'])) {
          continue;
        }
        $diffs = reset(reset($diff[$paragraph_id]['diff'][$diff_field]));
        $diff_rows = [];
        for ($i = 0; $i < count($diffs); $i += 2) {
          $diff_rows[] = [$diffs[$i], $diffs[$i + 1]];
        }
        $row['top']['summary'][$diff_field]['data'] = [
          '#type' => 'table',
          '#rows' => $diff_rows,
          '#attributes' => ['class' => ['relative', 'diff-context-wrapper']],
        ];
      }
      $row['top']['summary']['author']['data']['#markup'] = $author;
      $form['widget'][] = $row;
    }
    $form['#attached']['library'][] = 'diff/diff.colors';
    $form['widget']['#is_diff_form'] = TRUE;


//    $assessment_edit_form = $form = $this->entityFormBuilder()->getForm($paragraph_id, 'geysir_modal_edit', []);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('See differences', $form, ['width' => '80%']));
    return $response;
  }

  public static function getTableCellMarkup($markup, $class, $span = 1) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'paragraph-summary-component',
          "paragraph-summary-component-$class",
          "paragraph-summary-component-span-$span",
        ],
      ],
      'data' => ['#markup' => $markup],
    ];
  }

  /**
   * Callback for opening the diff modal form.
   */
  public function openDiffModalForm() {
    $response = new AjaxResponse();


//    $type = $request->query->get('type');
    $type = 'value';
    $build = [
      '#theme' => 'diff_prototype_modal',
      '#values' => !empty($type) ? $type : '',
    ];

    $build['#attached']['library'][] = 'diff/diff.general';
    $build['#attached']['library'][] = 'diff/diff.colors';
    $build['#attached']['library'][] = 'diff/diff.single_column';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.widget';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.actions';
    $build['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
    $build['#attached']['library'][] = 'iucn_who_diff/iucn_who_diff.diff_prototype';
    $build['#attached']['library'][] = 'chosen/drupal.chosen';

    $chosen_conf = \Drupal::config('chosen.settings');

    $css_disabled_themes = $chosen_conf->get('disabled_themes');
    if (empty($css_disabled_themes)) {
      $css_disabled_themes = [];
    }

    $theme_name = \Drupal::theme()->getActiveTheme()->getName();
    if (!in_array($theme_name, $css_disabled_themes, TRUE)) {
      $build['#attached']['library'][] = 'chosen_lib/chosen.css';
    }

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Diff Modal', $build, ['width' => '1200']));


    return $response;
  }
}