<?php

namespace Drupal\iucn_who_homepage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\iucn_who_core\Sites\SitesQueryUtil;
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
    $search_view->setDisplay('sites_search_page_database');

    $sites = SitesQueryUtil::getPublishedSites();
    $site_options = [];
    foreach ($sites as $node) {
      $site_options[$node->id()] = $node->title->value;
    }
    sort($site_options);
    $site_options[0] = $this->t('Search for a site');
    return [
      'q' => [
        '#type' => 'select',
        '#title' => $this->t('Explore natural sites'),
        '#options' => $site_options,
      ],
      'send' => [
        '#type' => 'submit',
        '#title' => '',
      ],
      'advanced_search' => [
        '#markup' => Link::fromTextAndUrl(
          $this->t('Advanced search'),
          Url::fromRoute('view.sites_search.sites_search_page_database')
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
    $q = $form_state->getValue('q');
    $results = SitesQueryUtil::searchSiteByName($q);
    // One result sends the user to the hit's page.
    if (count($results) == 1) {
      $node = reset($results);
      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }
    else {
      $form_state->setRedirect('view.sites_search.sites_search_page_database', ['keys' => $q]);
    }
  }
}
