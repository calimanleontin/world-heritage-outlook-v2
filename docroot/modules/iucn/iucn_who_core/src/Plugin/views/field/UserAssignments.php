<?php

/**
 * @file
 * Definition of Drupal\iucn_who_core\Plugin\views\field\UserAssignments
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
 * @ViewsField("iucn_who_core_user_assignments")
 */
class UserAssignments extends FieldPluginBase {

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
    $options['disable_link'] = ['default' => FALSE];
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['disable_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Link'),
      '#default_value' => $this->options['disable_link'],
      '#weight' => -101,
    ];
  }

  public function appendAssignments(&$nodes, $display_id, $field, $group_name) {
    $view = Views::getView('people_assignments');
    $view->setDisplay($display_id);
    $view->execute();
    foreach ($view->result as $row) {
      if (!$row->uid) {
        continue;
      }
      $nid = $row->_relationship_entities[$field]->id();
      if (!$nid) {
        continue;
      }
      if (empty($nodes[$row->uid])) {
        $nodes[$row->uid] = [];
      }
      $node = $row->_relationship_entities[$field];
      $site = $node->field_as_site->entity;
      if ($site) {
        $title = $site->getTitle();
        $nid = $site->id();
      }
      else {
        $title = $node->getTitle();
      }
      $nodes[$row->uid][$nid]['title'] = $title;
      if (empty($nodes[$row->uid][$nid]['groups'])) {
        $nodes[$row->uid][$nid]['groups'] = [];
      }
      $nodes[$row->uid][$nid]['groups'][] = $group_name;
    }
    return $nodes;
  }
  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $user = $values->_entity;
    $nodes = &drupal_static(__CLASS__ . __FUNCTION__);
    if (!$nodes) {
      $nodes = [];
      $this->appendAssignments($nodes, 'assessors', 'reverse__node__field_assessor', 'Assessor');
      $this->appendAssignments($nodes, 'coordinators', 'reverse__node__field_coordinator', 'Coordinator');
      $this->appendAssignments($nodes, 'reviewers', 'reverse__node__field_reviewers', 'Reviewer');
    }
    if (!empty($nodes[$user->id()])) {
      $assignments = $nodes[$user->id()];
      foreach ($assignments as $nid => $value) {
        $assignments[$nid] = $value['title'] . ' (' . implode(', ', $value['groups']) . ')';
        if (!$this->options['disable_link']) {
          $url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => FALSE]);
          $assignments[$nid] = Link::fromTextAndUrl($assignments[$nid], $url_object)->toString();
        }
      }
      sort($assignments);
      return  [
        '#items' => $assignments,
        '#list_type' => 'ol',
        '#theme' => 'item_list',
      ];
    }
    else {
      return "";
    }
  }
}
