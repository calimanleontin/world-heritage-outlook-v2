<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\linkit\ProfileInterface;
use Drupal\paragraphs\ParagraphInterface;

class IucnModalParagraphDeleteForm extends FormBase {

  /**
   * @var EntityInterface
   */
  protected $parent_entity;

  /**
   * @var ParagraphInterface
   */
  protected $paragraph;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_paragraph_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['actions'] = ['#type' => 'actions'];

    $this->paragraph = $this->getRouteMatch()->getParameter('paragraph_revision');
    $this->parent_entity = $this->getRouteMatch()->getParameter('node_revision');

    $can_delete = TRUE;
    $blocking_threats = [];
    // Values cannot be deleted if there is at least one threat
    // with exactly one referenced value - this paragraph.
    if (in_array($this->paragraph->bundle(), ['as_site_value_wh', 'as_site_value_bio'])) {
      $threats = array_merge($this->parent_entity->get('field_as_threats_current')->getValue(), $this->parent_entity->get('field_as_threats_potential')->getValue());
      $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
      foreach ($threats as $threat) {
        /** @var \Drupal\paragraphs\ParagraphInterface $threat_paragraph */
        $threat_paragraph = $paragraph_storage->loadRevision($threat['target_revision_id']);
        $affected_values = array_merge($threat_paragraph->get('field_as_threats_values_wh')->getValue(), $threat_paragraph->get('field_as_threats_values_bio')->getValue());
        if (count($affected_values) != 1) {
          continue;
        }
        $affected_value = $affected_values[0]['target_revision_id'];
        if ($affected_value == $this->paragraph->getRevisionId()) {
          $blocking_threats[] = $threat_paragraph->field_as_threats_threat->value;
          $can_delete = FALSE;
        }
      }
    }

    if ($can_delete) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="delete-warning">' . $this->t('Are you sure you want to delete this row? This action cannot be reverted.') . '</div>',
      ];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#ajax' => [
          'callback' => '::ajaxDelete',
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
            'message' => NULL,
          ],
          'disable-refocus' => TRUE,
        ],
        '#attributes' => ['class' => ['button--primary']],
      ];
    }
    else {
      $warning = $this->t('This value cannot be deleted because it is the only affected value for the some threats. Please edit or delete these threats first:');
      $form['warning'] = [
        '#type' => 'container',
        '#prefix' => '<div role="contentinfo" aria-label="Warning message" class="messages messages--warning">',
        '#suffix' => '</div>',
      ];
      $form['warning']['message'] = [
        '#type' => 'markup',
        '#markup' => '<div class="delete-warning">' . $warning . '</div>',
      ];
      $form['warning']['threats'] = [
        '#items' => $blocking_threats,
        '#theme' => 'item_list',
        '#list_type' => 'ul',
      ];
      $form['actions']['go_to_threats'] = [
        '#type' => 'link',
        '#title' => $this->t('Go to threats'),
        '#url' => $this->parent_entity->toUrl('edit-form', ['query' => ['tab' => 'threats']]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }

    IucnModalForm::buildCancelButton($form);

    return $form;
  }

  public function ajaxDelete(&$form, FormStateInterface $form_state) {
    $field = $this->getRouteMatch()->getParameter('field');

    $field_values = $this->parent_entity->get($field)->getValue();
    $key = array_search($this->paragraph->id(), array_column($field_values, 'target_id'));
    $this->parent_entity->get($field)->removeItem($key);
    $this->parent_entity->save();
    return IucnModalForm::assessmentAjaxSave($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return [];
  }

}
