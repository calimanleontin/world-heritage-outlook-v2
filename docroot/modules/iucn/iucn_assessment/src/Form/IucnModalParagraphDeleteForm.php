<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalParagraphDeleteForm extends IucnModalParagraphForm {

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $parentEntity;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['actions'] = ['#type' => 'actions'];
    $form['#attributes']['class'][] = 'paragraph-form';

    $can_delete = TRUE;
    $blocking_threats = [];
    // Values cannot be deleted if there is at least one threat
    // with exactly one referenced value - this paragraph.
    if (in_array($this->entity->bundle(), ['as_site_value_wh', 'as_site_value_bio'])) {
      $threats = array_merge($this->nodeRevision->get('field_as_threats_current')->getValue(), $this->nodeRevision->get('field_as_threats_potential')->getValue());
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
      foreach ($threats as $threat) {
        /** @var \Drupal\paragraphs\ParagraphInterface $threat_paragraph */
        $threat_paragraph = $paragraph_storage->loadRevision($threat['target_revision_id']);
        $affected_values = array_merge($threat_paragraph->get('field_as_threats_values_wh')->getValue(), $threat_paragraph->get('field_as_threats_values_bio')->getValue());
        if (count($affected_values) != 1) {
          continue;
        }
        $affected_value = $affected_values[0]['target_revision_id'];
        if ($affected_value == $this->entity->getRevisionId()) {
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
        '#url' => $this->nodeRevision->toUrl('edit-form', ['query' => ['tab' => 'threats']]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }

    $this->buildCancelButton($form);

    return $form;
  }

  /**
   * Ajax callback for the delete button.
   *
   * @param $form
   * @param FormStateInterface $form_state
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function ajaxDelete(&$form, FormStateInterface $form_state) {
    $paragraph = $this->entity;

    $field_values = $this->nodeRevision->get($this->fieldName)->getValue();
    $key = array_search($this->entity->id(), array_column($field_values, 'target_id'));
    $this->nodeRevision->get($this->fieldName)->removeItem($key);
    $this->nodeRevision->save();

    $affected_value_fields = [
      'as_site_value_wh' => 'field_as_threats_values_wh',
      'as_site_value_bio' => 'field_as_threats_values_bio',
    ];

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    if (in_array($paragraph->bundle(), array_keys($affected_value_fields))) {
      $threats = array_merge($this->nodeRevision->get('field_as_threats_current')->getValue(), $this->nodeRevision->get('field_as_threats_potential')->getValue());
      foreach ($threats as $threat) {
        $threat = $paragraph_storage->loadRevision($threat['target_revision_id']);
        $field = $affected_value_fields[$paragraph->bundle()];
        $affected_values = $threat->get($field)->getValue();
        foreach ($affected_values as $idx => $affected_value) {
          if ($affected_value['target_revision_id'] == $paragraph->getRevisionId()) {
            unset($affected_values[$idx]);
            $threat->set($field, $affected_values);
            $threat->save();
            break;
          }
        }
      }
    }
    $paragraph->delete();

    return $this->ajaxSave($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * The save() method is not used in ContentEntityConfirmFormBase. This
   * overrides the default implementation that saves the entity.
   *
   * Confirmation forms should override submitForm() instead for their logic.
   */
  public function save(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   *
   * The delete() method is not used in ContentEntityConfirmFormBase. This
   * overrides the default implementation that redirects to the delete-form
   * confirmation form.
   *
   * Confirmation forms should override submitForm() instead for their logic.
   */
  public function delete(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Override the default validation implementation as it is not necessary
    // nor possible to validate an entity in a confirmation form.
    return $this->entity;
  }

}
