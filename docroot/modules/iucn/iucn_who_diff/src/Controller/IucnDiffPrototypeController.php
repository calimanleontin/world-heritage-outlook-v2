<?php

/**
* @file
* Contains \Drupal\iucn_who_diff\Controller\IucnDiffPrototypeController.
*/
namespace Drupal\iucn_who_diff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;


class IucnDiffPrototypeController extends ControllerBase {
  public function content(NodeInterface $node, Request $request) {
    $node_create_form = $this->entityFormBuilder()->getForm($node);
    // dpm(array_keys($node_create_form['#fieldgroups']));
    // $form = ''

    $build = [
      '#theme' => 'diff_prototype',
      // '#values' => render($node_create_form)
    ];

    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.widget';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.actions';
    $build['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';

    return $build;
  }

  public function modal(Request $request) {
    $build = [
      '#theme' => 'diff_prototype_modal',
      // '#values' => render($node_create_form)
    ];

    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.widget';
    $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.actions';
    // $build['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';

    return $build;
  }
}
