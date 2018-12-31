/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */
(function ($, Drupal, _) {

    'use strict';

    Drupal.behaviors.paragraphDiff = {
        attach: function (context) {
            $('a.diff-button').on('click', function() {
                var key = $(this).data('key');
                var selector = '#' + $(this).data('selector');
                var type = $(this).data('type');

                if (type == 'checkboxes') {

                    var values = drupalSettings.diff[key];
                    $(selector + ' input.form-checkbox').prop('checked', false);
                    for (var i = 0; i < values.length; i++) {
                        $(selector + '-' + values[i]).prop('checked', true);
                    }

                } else if (type == 'checkbox') {

                    if (drupalSettings.diff[key]) {
                        $(selector).prop('checked', true);
                    }
                    else {
                        $(selector).prop('checked', false);
                    }

                } else {

                    $(selector).val(drupalSettings.diff[key]);

                }

            });
        }
    };

})(jQuery, Drupal, _);
