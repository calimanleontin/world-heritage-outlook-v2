<?php

/**
 * @file
 * Drush integration for the iucn_pdf module.
 */

/**
 * Implements hook_drush_command().
 */
function iucn_pdf_drush_command() {
  $items['queue-pdf'] = [
    'description' => 'Queue all Pdf files',
    'core' => ['8+'],
    'aliases' => ['qpdf'],
    'arguments' => [],
  ];
  return $items;
}

/**
 * Put all site assessments into pdf queue
 */
function drush_iucn_pdf_queue_pdf($split = NULL) {
  /** @var \Drupal\iucn_pdf\PrintPdf $print_pdf */
  $print_pdf = \Drupal::service('iucn_pdf.print_pdf');
  $count = $print_pdf->queueAllPdfFiles();
  drush_print('Added ' . $count . ' site assessments into pdf queue!' );
}
