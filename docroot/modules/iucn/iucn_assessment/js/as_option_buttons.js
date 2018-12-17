/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.AsOptionsButtonsWidget = {
    attach: function (context) {
      $('.options-groups', context).change(function() {
        var $items = $('.as-checkboxes .form-checkbox', context);
        $items.each(function() {
          $(this).prop('checked', false);
        });
      })
    },
  };

  Drupal.behaviors.AsOptionsButtonsWidgetHideSubcategory = {
    attach: function (context) {
      var updateLabel = function (label) {
        var $items = $('.as-checkboxes .form-checkbox', context);

        var visible = false;
        $items.each(function() {
          if ($(this).is(':visible')) {
            visible = true;
          }
        });

        if (visible === false) {
          label.hide();
        }
        else {
          label.show();
        }
      }

      var label = $('.as-checkboxes-label', context)
      setTimeout(function () {
        updateLabel(label);
      }, 0, context);

      $('.options-groups', context).change(function () {
        setTimeout(function () {
          updateLabel(label);
        }, 0, context);
      });
    },
  }
})(jQuery, Drupal, _);
