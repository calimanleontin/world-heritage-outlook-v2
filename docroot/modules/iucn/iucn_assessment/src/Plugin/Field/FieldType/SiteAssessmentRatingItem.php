<?php

namespace Drupal\iucn_assessment\Plugin\Field\FieldType;

use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Variant of the 'rating image' field that links to the current company.
 *
 * @FieldType(
 *   id = "site_assessments_rating",
 *   label = @Translation("Rating Image"),
 *   description = @Translation("Rating image"),
 *   default_widget = "image_image",
 *   default_formatter = "image",
 *   column_groups = {},
 *   list_class = "\Drupal\iucn_assessment\Plugin\Field\FieldList\SiteAssessmentRatingItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class SiteAssessmentRatingItem extends ImageItem {

  /**
   * Whether or not the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  protected $fid = NULL;

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
    return $this->fid === NULL;
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
      $index = $this->getValue()['target_id'];
      $this->fid = NULL;
      $value = ['target_id' => NULL];
      $entity = $this->getEntity();
      if ($entity->hasField('field_assessments') && $entity->field_assessments->count()) {
        foreach ($entity->field_assessments as $idx => $item) {
          if ($idx != $index || empty($item->entity)) {
            continue;
          }
          if (!empty($item->entity->field_as_global_assessment_level->entity)
            && $item->entity->field_as_global_assessment_level->entity->hasField('field_image')
            && $item->entity->field_as_global_assessment_level->entity->field_image->count()) {
            $this->fid = $item->entity->field_as_global_assessment_level->entity->field_image->getValue()[0]['target_id'];
            $value = [
              'target_id' => $this->fid,
            ];
            break;
          }
        }
      }
      $this->setValue($value);
    }
  }

}
