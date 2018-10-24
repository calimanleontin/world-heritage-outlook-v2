/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.rowParagraphWidget = {
    attach: function (context) {
      $('.field--widget-row-entity-reference-paragraphs table tbody tr:first-child', context)
        .removeClass('draggable')
        .find('.field-multiple-drag').html('');
    }
  };

  Drupal.behaviors.rowParagraphFixedActions = {
    attach: function(context, settings) {
      $(function() {
        var $items = $('.field--widget-row-entity-reference-paragraphs .paragraphs-actions', context),
        inheritParentDims = function() {
            $items.each(function() {
                let $parent = $(this).parent();
                let $parentHeight = $parent.height();
                let $parentWidth = $parent.width();
                $(this).height($parentHeight);
                $(this).width($parentWidth);
                $parent.css('min-height', $parentHeight);
                $parent.addClass('processed');
            });
        };

        inheritParentDims();
        $(window).once("bind-to-window").resize(inheritParentDims);
      });
    }
  };

})(jQuery, Drupal);
