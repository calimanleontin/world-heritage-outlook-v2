<?php

namespace Drupal\iucn_who_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class IucnSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iucn_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'iucn_who.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iucn_who.settings');

    $form['assessment_year'] = array(
      '#type' => 'select',
      '#options' => [2014 => '2014', 2017 => '2017', 2020 => '2020'],
      '#title' => $this->t('Current assessment year'),
      '#default_value' => $config->get('assessment_year'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('iucn_who.settings');
    $new_year = $form_state->getValue('assessment_year');
    $prev_year = $config->get('assessment_year');

    $content_filters = [
      'type' => 'site_assessment',
      'field_as_cycle_value' => $new_year,
      'items_per_page' => 500,
    ];

    if ($new_year > $prev_year) {
      $url = Url::fromUserInput('/admin/content', ['query' => $content_filters]);
      $link = Link::fromTextAndUrl('Content', $url);
      $message = $this->t('You have changed the assessment year with a newer one.
        In order to update all sites with this change, please go to %link 
        and publish all @year assessments using Action field.',
        [
          '%link' => $link->toString(),
          '@year' => $new_year,
        ]
      );
      drupal_set_message($message, 'warning');
    }
    elseif ($new_year < $prev_year) {
      $content_filters['field_as_cycle_value'] = $prev_year;
      $url = Url::fromUserInput('/admin/content', ['query' => $content_filters]);
      $link = Link::fromTextAndUrl('Content', $url);
      $message = $this->t('You have changed the assessment year with an older one.
        In order to update all sites with this change, please go to %link 
        and un-publish all @year assessments using Action field.',
        [
          '%link' => $link->toString(),
          '@year' => $prev_year,
        ]
      );
      drupal_set_message($message, 'warning');
    }

    $config->set('assessment_year', $new_year)->save();

    parent::submitForm($form, $form_state);
  }
}
