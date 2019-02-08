/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.selectOptionsColors = {
    attach: function (context) {
      setTimeout(function () {
        var $items = $('select', context);
        $items.each(function () {
          var tid = $(this).val();
          if (drupalSettings.terms_colors[tid]) {
            $(this).parent().parent().attr('data-color', drupalSettings.terms_colors[tid]);
            $(this).parent().parent().addClass(drupalSettings.terms_colors[tid]);
          }
        });
      });

      $('select', context).change(function() {
        var tid = $(this).val();
        if (drupalSettings.terms_colors[tid]) {
          var old_class = $(this).parent().parent().attr('data-color');
          $(this).parent().parent().removeClass(old_class);
          $(this).parent().parent().attr('data-color', drupalSettings.terms_colors[tid]);
          $(this).parent().parent().addClass(drupalSettings.terms_colors[tid]);
        }
      })
    },
  };

})(jQuery, Drupal, _);
