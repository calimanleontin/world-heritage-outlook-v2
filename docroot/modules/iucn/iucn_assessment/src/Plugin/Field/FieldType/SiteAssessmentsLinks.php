<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldType;

use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Variant of the 'link' field that links to the current company.
 *
 * @FieldType(
 *   id = "site_assessments_link",
 *   label = @Translation("Site assessment link"),
 *   description = @Translation("A links list to site assessments."),
 *   default_widget = "link_default",
 *   default_formatter = "link",
 *   constraints = {"LinkType" = {}, "LinkAccess" = {}, "LinkExternalProtocols" = {}, "LinkNotExistingInternal" = {}}
 * )
 */
class SiteAssessmentsLinks extends LinkItem {

  /**
   * Whether or not the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }
  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensureCalculated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if (!$this->isCalculated) {
      $this->ensureCalculated();
      return parent::getValue();
    }
    else {
      return parent::getValue();
    }

  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $this->isCalculated = TRUE;
      $index = $this->getValue()['uri'];
      $value = NULL;
      $entity = $this->getEntity();
      if ($entity->hasField('field_assessments') && $entity->field_assessments->count()) {
        foreach ($entity->field_assessments as $idx => $item) {
          if ($idx != $index || empty($item->entity)) {
            continue;
          }
          $active = iucn_assessment_year_display($entity);
          $value = [
            'uri' => $entity->toUrl()->setOption('query', ['year' => $item->entity->field_as_cycle->value])->toUriString(),
            'title' => $item->entity->field_as_cycle->value,
          ];
          if ($item->entity->field_as_cycle->value == $active) {
            $value['_attributes']['class'][] = 'active-year';
          }
          break;
        }
      }
      $this->setValue($value);
    }
  }

}
