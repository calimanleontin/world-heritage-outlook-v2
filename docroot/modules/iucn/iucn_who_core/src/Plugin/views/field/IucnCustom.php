<?php

namespace Drupal\iucn_who_core\Plugin\views\field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\field\Custom;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;

/**
 * @ingroup views_field_handlers
 *
 * @ViewsField("iucn_custom")
 */
class IucnCustom extends Custom {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['identifier'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    unset($form['alter']);
    $form['identifier'] = [
      '#type' => 'select',
      '#title' => $this->t('Identifier'),
      '#default_value' => $this->options['identifier'],
      '#options' => [
        'rating_category_label' => 'Rating category label',
        'rating_change_2014_2017' => 'Value change (2014 and 2017)',
        'rating_change_2017_2020' => 'Value change (2017 and 2020)',
        'rating_change_2014_2020' => 'Value change (2014 and 2020)',
      ],
      '#required' => TRUE,
      '#weight' => -103,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (empty($values->field) || empty($values->fieldLabel)) {
      return NULL;
    }

    if (empty($values->_entity) || !$values->_entity instanceof Node || $values->_entity->bundle() !== 'site') {
      return NULL;
    }

    switch ($this->options['identifier']) {
      case 'rating_category_label':
        return ViewsRenderPipelineMarkup::create(
          Xss::filterAdmin($values->fieldLabel));
        break;

      case 'rating_change_2014_2017':
        return $this->getRatingChange($values->_entity, 2014, 2017, $values->field);
        break;

      case 'rating_change_2017_2020':
        return $this->getRatingChange($values->_entity, 2017, 2020, $values->field);
        break;

      case 'rating_change_2014_2020':
        return $this->getRatingChange($values->_entity, 2014, 2020, $values->field);
        break;
    }

    return NULL;
  }

  /**
   * @param \Drupal\node\Entity\Node $site
   * @param $cycle1
   * @param $cycle
   * @param $field
   *
   * @return string|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRatingChange(Node $site, $cycle1, $cycle, $field) {
    $assessment1 = $this->getAssessmentForCycle($site, $cycle1);
    $assessment2 = $this->getAssessmentForCycle($site, $cycle);
    if (empty($assessment1) || empty($assessment2)) {
      return NULL;
    }

    $value1 = $assessment1->get($field)->entity ?
      $assessment1->get($field)->entity->label() :
      NULL;

    $value2 = $assessment2->get($field)->entity ?
      $assessment2->get($field)->entity->label() :
      NULL;

    if (empty($value1) && empty($value2)) {
      return  NULL;
    }

    return $value1 . ' > ' . $value2;
  }

  /**
   * @param \Drupal\node\Entity\Node $site
   * @param $cycle
   *
   * @return Node
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAssessmentForCycle(Node $site, $cycle) {
    $assessment = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(
        [
          'field_as_site.target_id' => $site->id(),
          'field_as_cycle' => $cycle,
        ]);

    return !empty($assessment) ? reset($assessment) : NULL;
  }

}
