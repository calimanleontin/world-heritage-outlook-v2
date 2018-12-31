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
                var data_selector = '[data-drupal-selector="' + $(this).data('selector') + '"]';
                var type = $(this).data('type');
                update(key, data_selector, type);
                var key2 = $(this).data('key2');
                if (key2) {
                    var data_selector2 = '[data-drupal-selector="' + $(this).data('selector2') + '"]';
                    update(key2, data_selector2, type);
                }
            });

            function update(key, data_selector, type) {
                if (type == 'checkboxes') {

                    var values = drupalSettings.diff[key];
                    $(data_selector + ' input.form-checkbox').prop('checked', false);
                    for (var i = 0; i < values.length; i++) {
                        $('[data-drupal-selector="' + $(this).data('selector') + '-' + values[i] + '"]').prop('checked', true);
                    }

                } else if (type == 'checkbox') {

                    if (drupalSettings.diff[key]) {
                        $(data_selector).prop('checked', true);
                    }
                    else {
                        $(data_selector).prop('checked', false);
                    }

                } else if (type == 'select') {
                    if (drupalSettings.diff[key].length) {
                        $(data_selector).val(drupalSettings.diff[key]);
                    } else {
                        $(data_selector).val(['_none']);
                    }

                } else {

                    $(data_selector).val(drupalSettings.diff[key]);

                }

            }
        }
    };

})(jQuery, Drupal, _);
