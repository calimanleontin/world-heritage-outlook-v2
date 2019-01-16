<?php

namespace Drupal\iucn_assessment\Form;

trait DiffModalTrait {

  public function getTableCellMarkup($markup, $class, $span = 1, $weight = 0) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'paragraph-summary-component',
          "paragraph-summary-component-$class",
          "paragraph-summary-component-span-$span",
        ],
      ],
      'data' => ['#markup' => $markup],
      '#weight' => $weight,
    ];
  }

  public function getDiffMarkup($diff) {
    $diff_rows = [];
    foreach ($diff as $diff_group) {
      foreach ([0,2] as $i) {
        if (!empty($diff_group[$i + 1]['data']['#markup']) && !empty($diff_group[$i + 3]['data']['#markup'])
          && $diff_group[$i + 1]['data']['#markup'] == $diff_group[$i + 3]['data']['#markup']) {
          continue;
        }
        $diff_rows[] = [$diff_group[$i], $diff_group[$i + 1]];
      }
    }
    return $diff_rows;
  }

  public function addAuthorCell(array &$table, $key, $markup, $class, $span = 1, $weight = 0) {
    foreach ($table['#attributes']['class'] as &$class) {
      if (preg_match('/paragraph-top-col-(\d+)/', $class, $matches)) {
        $col_count = $matches[1] + $span - 1;
        $class = "paragraph-top-col-$col_count";
      }
    }

    $table[$key]['author'] = $this->getTableCellMarkup($markup, $class, $span, $weight);
  }

}
