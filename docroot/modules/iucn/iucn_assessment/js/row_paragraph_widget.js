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
        $(window).once("bind-to-window").resize(inheritParentDims);

        // var resizeTimer;
        // $('textarea', context).on('resize', function() {
        //   console.log('resize');
        //   inheritParentDims();
        //   // clearTimeout(resizeTimer);
        //   // resizeTimer = setTimeout(function() {
        //   // }, 250);
        // });

        // var $textareas = jQuery('textarea');

         // // store init (default) state
         // $textareas.data('x', $textareas.outerWidth());
         // $textareas.data('y', $textareas.outerHeight());

         // $textareas.mouseup(function(){

         //    var $this = jQuery(this);

         //    if (  $this.outerWidth()  != $this.data('x')
         //       || $this.outerHeight() != $this.data('y') )
         //    {
         //        // Resize Action Here
         //        alert( $this.outerWidth()  + ' - ' + $this.data('x') + '\n'
         //             + $this.outerHeight() + ' - ' + $this.data('y')
         //             );
         //    }

         //    // store new height/width
         //    $this.data('x', $this.outerWidth());
         //    $this.data('y', $this.outerHeight());
         // });

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

})(jQuery, Drupal);
