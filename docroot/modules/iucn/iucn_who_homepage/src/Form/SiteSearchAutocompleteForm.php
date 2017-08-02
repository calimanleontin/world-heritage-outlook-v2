<?php

namespace Drupal\iucn_who_homepage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Link;
use Drupal\views\Views;

class SiteSearchAutocompleteForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'iucn_who_homepage_site_search_autocomplete';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $search_view = Views::getView('sites_search');
    $search_view->setDisplay('sites_search_page');

    return [
      'q' => [
        '#type' => 'textfield',
        '#title' => $this->t('Explore natural sites'),
        '#autocomplete_route_name' => 'iucn.ajax.site-name-search',
      ],
      'send' => [
        '#type' => 'button',
        '#title' => '',
      ],
      'advanced_search' => [
        '#markup' => Link::fromTextAndUrl(
          $this->t('Advanced search'),
          $search_view->getUrl()
        )->toString(),
      ],
    ];

  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  }


  public function ajaxCallbackSearch() {

  }
}