<?php

namespace Drupal\iucn_assessment\Controller;

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
  public function openModalForm() {
    $response = new AjaxResponse();
    // Get the modal form using the form builder.
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\iucn_assessment\Form\ModalForm');
    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('My Modal Form', $modal_form, ['width' => '800']));
    return $response;
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