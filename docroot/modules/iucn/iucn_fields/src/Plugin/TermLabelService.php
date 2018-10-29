<?php

namespace Drupal\iucn_fields\Plugin;

class TermLabelService {

  public function getTermLabel($tid, $year) {
    $altered_term_labels = [
      2023 => [

      ],
      2020 => [
        1414 => 'Changes in traditional ways of life and knowledge systems that result in negative impact',
        1415 => 'Identity/social cohesion/ changes in local population and community that result in negative impact',
        1404 => 'Volcanic activity',
//        1358 => '',
        1345 => 'Law enforcement',
        1333 => 'Management system/plan',
        1334 => 'Effectiveness of management system/plan',
        1338 => 'Staff capacity, training, and development',
      ],
      2017 => [
        1331 => 'Legal framework',
        1341 => 'Tourism and visitation management',
        1384 => 'Hunting (commercial/subsistence)',
        1385 => 'Poaching',
        1411 => 'Ocean acidification',
        1412 => 'Temperature extremes',
      ],
    ];
    foreach ($altered_term_labels as $key => $altered_term_label) {
      if ($key > $year) {
        continue;
      }
      if (!empty($altered_term_label[$tid])) {
        return t($altered_term_label[$tid]);
      }
    }
    return NULL;
  }
}
