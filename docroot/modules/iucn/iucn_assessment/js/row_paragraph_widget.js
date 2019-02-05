/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.rowParagraphWidget = {
    attach: function (context) {
      $('.field--widget-row-entity-reference-paragraphs table tbody tr:first-child', context)
        .removeClass('draggable')
        .find('.field-multiple-drag').html('');
      $('tr.draggable', context).once('deletedParagraph').each(function () {
        if ($(this).find('.paragraph-deleted-row').length !== 0) {
          $(this).addClass('paragraph-deleted-row');
        }
      });
      $('tr.draggable', context).once('newParagraph').each(function () {
        if ($(this).find('.paragraph-new-row').length !== 0) {
          $(this).addClass('paragraph-new-row');
        }
      });
      $('tr.draggable', context).once('diffParagraph').each(function () {
        if ($(this).find('.paragraph-diff-row').length !== 0) {
          $(this).addClass('paragraph-diff-row');
        }
      });
      $('tr.draggable', context).once('removeDraggable').each(function () {
        if ($(this).find('.paragraph-no-tabledrag').length !== 0) {
          $(this).removeClass('draggable').find('.field-multiple-drag').html('');
        }
      });

      $('#drupal-modal').once('stickyHeader').on("scroll", function() {
        $(this).find('.diff-modal table.field-multiple-table > tbody > tr:first-child > td > div').css('top', $(this).scrollTop() + "px");
      });

      // Fix an issue with chosen elements getting focused on modal open.
      $('.chosen-container', context).once('fixModalChosen').each(function () {
        if (context !== document) {
          var input = $(this).find('input').first();
          input.unbind('focus');
        }
      });
    },
};

})(jQuery, Drupal, _);
