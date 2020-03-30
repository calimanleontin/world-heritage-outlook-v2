<?php

namespace Drupal\iucn_assessment;

use Drupal\Component\Diff\WordLevelDiff;
use Drupal\diff\DiffFormatter;

/**
 * Diff formatter which returns output that can be rendered to a table.
 */
class AssessmentDiffFormatter extends DiffFormatter {

  /**
   * {@inheritdoc}
   */
  protected function _changed($orig, $closing) {
    $orig = array_map('\Drupal\Component\Utility\Html::escape', $orig);
    $closing = array_map('\Drupal\Component\Utility\Html::escape', $closing);
    $diff = new WordLevelDiff($orig, $closing);
    $del = $diff->orig();
    $add = $diff->closing();

    // Notice that WordLevelDiff returns HTML-escaped output. Hence, we will be
    // calling addedLine/deletedLine without HTML-escaping.
    while ($line = array_shift($del)) {
      $aline = array_shift($add);
      if (!empty($aline) && strpos($aline, '<span class="diffchange">') === FALSE) {
        unset($aline);
      }

      if (!empty($line) && strpos($line,  '<span class="diffchange">') === FALSE) {
        unset($line);
      }

      $addedLine = isset($aline) ? $this->addedLine($aline) : $this->emptyLine();
      $deletedLine = isset($line) ? $this->deletedLine($line) : $this->emptyLine();
      $this->rows[] = array_merge($deletedLine, $addedLine);
    }

    // If any leftovers.
    foreach ($add as $line) {
      $this->rows[] = array_merge($this->emptyLine(), $this->addedLine($line));
    }
  }

}
