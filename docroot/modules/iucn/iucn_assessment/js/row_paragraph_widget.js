/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.rowParagraphWidget = {
    attach: function (context) {
      var $context = $(context);
      $context.find('.field--widget-row-entity-reference-paragraphs table tbody tr:first-child').removeClass('draggable');
    }
  };

})(jQuery, Drupal);
