<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;

class IucnModalParagraphDeleteForm extends IucnModalParagraphConfirmationForm {

  protected $affectedValuesFields = [
    'as_site_value_wh' => 'field_as_threats_values_wh',
    'as_site_value_bio' => 'field_as_threats_values_bio',
  ];

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Delete row');
    $form['warning']['#value'] = $this->t('Are you sure you want to delete this row? This action cannot be reverted.');
    $form['actions']['submit']['#value'] = $this->t('Delete');

    if (!in_array($this->entity->bundle(), array_keys($this->affectedValuesFields))) {
      // No more validation is required.
      return $form;
    }

    // Values cannot be deleted if there is at least one threat with exactly one
    // referenced value - this paragraph.
    $blocking_threats = [];
    $threats = array_merge($this->nodeRevision->get('field_as_threats_current')->getValue(), $this->nodeRevision->get('field_as_threats_potential')->getValue());
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    foreach ($threats as $threat) {
      /** @var \Drupal\paragraphs\ParagraphInterface $threat_paragraph */
      $threat_paragraph = $paragraph_storage->loadRevision($threat['target_revision_id']);
      $affected_values = array_merge($threat_paragraph->get('field_as_threats_values_wh')->getValue(), $threat_paragraph->get('field_as_threats_values_bio')->getValue());
      if (count($affected_values) != 1) {
        continue;
      }
      $affected_value = $affected_values[0]['target_id'];
      if ($affected_value != $this->entity->id()) {
        continue;
      }
      $blocking_threats[] = $threat_paragraph->field_as_threats_threat->value;
    }

    if (!empty($blocking_threats)) {
      unset($form['warning']['#value']);
      unset($form['actions']);
      $form['warning']['#attributes']['class'] = ['messages', 'messages--warning'];
      $form['warning']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('This value cannot be deleted because it is the only affected value for the some threats. Please edit or delete these threats first:'),
      ];
      $form['warning']['threats'] = [
        '#items' => $blocking_threats,
        '#theme' => 'item_list',
        '#list_type' => 'ul',
      ];
      $form['actions']['go_to_threats'] = [
        '#type' => 'link',
        '#title' => $this->t('Go to threats tab'),
        '#url' => $this->nodeRevision->toUrl('edit-form', ['query' => ['tab' => 'threats']]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }

    return $form;
  }

  public function ajaxSave($form, FormStateInterface $form_state) {
    $paragraph = $this->entity;

    $field_values = $this->nodeRevision->get($this->fieldName)->getValue();
    $key = array_search($this->entity->id(), array_column($field_values, 'target_id'));
    $this->nodeRevision->get($this->fieldName)->removeItem($key);

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    if (in_array($paragraph->bundle(), array_keys($this->affectedValuesFields))) {
      foreach (['field_as_threats_current', 'field_as_threats_potential'] as $threatField) {
        $threats = $this->nodeRevision->get($threatField)->getValue();
        $threatIds = array_column($this->nodeRevision->get($threatField)->getValue(), 'target_id');

        foreach ($threats as $threat) {
          $threat = $paragraph_storage->loadRevision($threat['target_revision_id']);
          $field = $this->affectedValuesFields[$paragraph->bundle()];
          $affected_values = $threat->get($field)->getValue();
          $key = array_search($this->entity->id(), array_column($affected_values, 'target_id'));
          if ($key !== FALSE) {
            $threat->get($field)->removeItem($key);
            $threat->save();

            $threatKey = array_search($threat->id(), $threatIds);
            $this->nodeRevision->get($threatField)->set($threatKey, $threat);
          }
        }
      }
    }

    return parent::ajaxSave($form, $form_state);
  }
}
