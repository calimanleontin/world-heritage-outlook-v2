<?php

namespace Drupal\iucn_assessment\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

class ParagraphAsSiteBenefitForm {

  public static function alter(array &$form, FormStateInterface $form_state) {
    $form['field_as_benefits_category']['#element_validate'][] = [self::class, 'validateBenefitCategories'];
  }

  public static function validateBenefitCategories(array &$element, FormStateInterface $form_state, array &$form) {
    $values = $form_state->getValue('field_as_benefits_category');
    if (empty($values) || count($values) == 1 && $values[0]['target_id'] == 0 ) {
      $form_state->setError($element, t('Category field is required'));
    }
    $selected_subcategory = FALSE;
    foreach ($values as $category) {
      if (!empty($element['widget']['options_groups']['#empty_groups'][$category['target_id']])
        || !isset($element['widget']['options_groups']['#options'][$category['target_id']])) {
        $selected_subcategory = TRUE;
        break;
      }
    }
    if (!$selected_subcategory) {
      $form_state->setError($element, t('Select at least one subcategory'));
    }
  }

}
