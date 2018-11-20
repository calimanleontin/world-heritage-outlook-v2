/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.AsOptionsButtonsWidget = {
    attach: function (context) {
      $('.options-groups', context).change(function() {
        var $items = $('.form-checkbox', context);
        $items.each(function() {
          var $parent = $(this).parent();
          if ($parent.is(":hidden")) {
            $(this).prop('checked', false);
          }
        });
      })
    },
  };
})(jQuery, Drupal, _);
