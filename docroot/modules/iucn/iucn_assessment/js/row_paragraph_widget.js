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
    },
};

  Drupal.behaviors.rowParagraphFixedActions = {
    attach: function(context, settings) {
      $(function() {
        var inheritParentDims = function() {
            var $items = $('.field--widget-row-entity-reference-paragraphs .paragraphs-actions', context);
            $items.each(function() {
                var $parent = $(this).parent();
                var $parentHeight = $parent.height();
                var $parentWidth = $parent.width();
                $(this).height($parentHeight);
                $(this).width($parentWidth);
                $parent.css('min-height', $parentHeight);
                $parent.addClass('processed');
            });
        };

        inheritParentDims();
        $(window).once("bind-to-window").on('resize', _.debounce(inheritParentDims, 100));
      });
    }
  };

  Drupal.behaviors.scrollAtStart = {
    attach: function (context, settings) {
      $(function() {
        $('.responsive-wrapper', context).scrollLeft(0);
      });
    }
  };

  Drupal.behaviors.doubleScrollBar = {
    attach: function (context, settings) {
        $(function(){
            $(".responsive-wrapper", context).each(function() {
                var $table = $(this).find('.field-multiple-table');
                $(this).siblings(".double-scrollbar-helper").find('.inner').width($table.width());
            });

            $(".responsive-wrapper", context).scroll(function(){
                $(this).siblings(".double-scrollbar-helper")
                    .scrollLeft($(this).scrollLeft());
            });
            $(".double-scrollbar-helper", context).scroll(function(){
                $(this).siblings(".responsive-wrapper")
                    .scrollLeft($(this).scrollLeft());
            });

            $(window).once("bind-dsb-to-window").on('resize', _.debounce(function(){
                $(".responsive-wrapper", context).each(function() {
                    var $table = $(this).find('.field-multiple-table');
                    $(this).siblings(".double-scrollbar-helper").find('.inner').width($table.width());
                });
            }, 100));
        });
    }
  };

})(jQuery, Drupal, _);
