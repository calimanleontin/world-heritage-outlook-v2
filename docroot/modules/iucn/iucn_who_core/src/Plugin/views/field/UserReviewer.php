<?php

/**
 * @file
 * Definition of Drupal\iucn_who_core\Plugin\views\field\UserReviewer
 */

namespace Drupal\iucn_who_core\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("iucn_who_core_reviewer")
 */
class UserReviewer extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $user = $values->_entity;
    $nodes = &drupal_static(__CLASS__ . __FUNCTION__);
    if (!$nodes) {
      $nodes = [];
      $display_id = 'reviewers';
      $view = Views::getView('people_assignments');
      $view->setDisplay($display_id);
      $view->execute();
      $nodes = [];
      foreach ($view->result as $row) {
        if (!$row->uid) {
          continue;
        }
        if (!$row->field_reviewers_users_field_data_nid) {
          continue;
        }

        if (empty($nodes[$row->uid])) {
          $nodes[$row->uid] = [];
        }
        $url_object = Url::fromRoute('entity.node.canonical', ['node' => $row->field_reviewers_users_field_data_nid], ['absolute' => FALSE]);
        $nodes[$row->uid][] = Link::fromTextAndUrl($row->_relationship_entities['reverse__node__field_reviewers']->getTitle(), $url_object)->toString();
      }
    }
    if (!empty($nodes[$user->id()])) {
      $build = array();
      $build['#markup'] = implode(", ", $nodes[$user->id()]);
      return $build;
    }
    else {
      return "";
    }
  }
}
