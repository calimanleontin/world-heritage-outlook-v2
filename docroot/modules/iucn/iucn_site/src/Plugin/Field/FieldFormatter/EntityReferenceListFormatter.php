<?php

namespace Drupal\iucn_site\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Component\Utility\Html;



/**
 * Plugin implementation of the 'entity reference list' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_list",
 *   label = @Translation("List"),
 *   description = @Translation("Display the label of the referenced entities separated by a custom separator."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceListFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link' => TRUE,
      'separator' => ',',
      'override_label' => 0,
      'singular' => '',
      'plural' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['link'] = [
      '#title' => t('Link label to the referenced entity'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('link'),
    ];

    $elements['separator'] = [
      '#title' => t('Entities separator'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->getSetting('separator'),
      '#description' => t("Enter separator symbol, this will be displayed between entities."),
    ];

    $elements['override_label'] = [
      '#title' => t('Override label'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('override_label'),
      '#description' => t("If this is checked make sure to set the LABEL display to - Hidden - ."),
    ];

    $elements['singular'] = [
      '#title' => t('Label for singular'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->getSetting('singular'),
      '#description' => t("Enter the label title, in english, that will be displayed when there are only one term. e.g. Criterion"),
    ];

    $elements['plural'] = [
      '#title' => t('Label for multiple'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->getSetting('plural'),
      '#description' => t("Enter the label title, in english, that will be displayed when there are only more then one term. e.g. Criterion"),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('link') ? t('Link to the referenced entity') : t('No link');
    if ($separator = $this->getSetting('separator')) {
      $summary[] = t('Separator:') . Html::escape($separator);
    }
    $summary[] = t('Override label:') . ($this->getSetting('override_label') ? 'Yes' : 'No');
    if ($this->getSetting('override_label')) {
      $summary[] = t('Label for singular:') . ($this->getSetting('singular') ? Html::escape($this->getSetting('singular')) : '-');
      $summary[] = t('Label for multiple:') . ($this->getSetting('plural') ? Html::escape($this->getSetting('plural')) : '-');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');
    $separator = Html::escape($this->getSetting('separator'));

    $entities = $this->getEntitiesToView($items, $langcode);
    $entities_count = count($entities);
    $count = 0;
    foreach ($entities as $delta => $entity) {
      $count++;
      $label = $entity->label();
      // If the link is to be displayed and the entity has a uri, display a
      // link.
      if ($output_as_link && !$entity->isNew()) {
        try {
          $uri = $entity->urlInfo();
        }
        catch (UndefinedLinkTemplateException $e) {
          // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
          // and it means that the entity type doesn't have a link template nor
          // a valid "uri_callback", so don't bother trying to output a link for
          // the rest of the referenced entities.
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$entity->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
          '#suffix' => ($count != $entities_count ? $separator : ''),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        $elements[$delta] = ['#plain_text' => $label, '#suffix' => ($count != $entities_count ? $separator : '')];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    if ($count == 0) {
      return $elements;
    }

    if ($this->getSetting('override_label')) {
      $label_title = '';
      if ($count == 1) {
        $label_title = $this->getSetting('singular') ? $this->getSetting('singular') : '';
      }
      elseif ($count > 1) {
        $label_title = $this->getSetting('plural') ? $this->getSetting('plural') : '';
      }

      $output = [
        0 => [
          '#plain_text' => t($label_title),
          '#prefix' => '<div class="field--label-inline"><div class="field--label field--label-list">',
          '#suffix' => '</div></div>',
        ],
      ];
      foreach ($elements as $element) {
        $output[] = $element;
      }
    }
    else {
      $output = $elements;
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

}
