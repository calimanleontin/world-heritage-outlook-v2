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
    public function content(Request $request) {
        // $node_create_form = $this->entityFormBuilder()->getForm($node);
        $diff = [];

        // if ($node->bundle() == 'site_assessment' && !$node->isNew()) {
        //   /** @var \Drupal\iucn_diff_revisions\Controller\DiffController $diffController */
        //   $diffController = \Drupal::service('iucn_diff_revisions.diff_controller');
        //   // The $results variable represents the configured diff table.
        //   // This variable will be saved in the revision's field that stores the diff data
        //   // dpm($node);
        //   // dpm($node->vid->value);
        //   $diff = $diffController->compareRevisions(4283,$node->vid->value);
        //   // dpm($diff);
        // }

        $build = [
          '#theme' => 'diff_prototype',
          // '#values' =>
        ];

        $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.widget';
        $build['#attached']['library'][] = 'paragraphs/drupal.paragraphs.actions';
        $build['#attached']['library'][] = 'diff/diff.general';
        $build['#attached']['library'][] = 'diff/diff.colors';
        $build['#attached']['library'][] = 'diff/diff.single_column';
        $build['#attached']['library'][] = 'iucn_assessment/iucn_assessment.row_paragraph';
        $build['#attached']['library'][] = 'iucn_who_diff/iucn_who_diff.diff_prototype';

        return $build;
    }

    public function modal(Request $request) {
        /** @var \Symfony\Component\HttpFoundation\ParameterBag $query */
        $type = $request->query->get('type');
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

        return $build;
    }

}
