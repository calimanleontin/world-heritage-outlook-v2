/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */
(function ($, Drupal, _) {

    'use strict';

    Drupal.behaviors.paragraphDiff = {
        attach: function (context) {
            $('a.diff-button').on('click', function() {
                var type = $(this).data('type');
                var key = $(this).data('key');
                var values = drupalSettings.diff[key];
                var selector = $(this).data('selector');
                update(values, selector, type);

                var key2 = $(this).data('key2');
                if (key2) {
                    var values2 = drupalSettings.diff[key2];
                    var selector2 = $(this).data('selector2');
                    update(values2, selector2, type);
                }
            });

            function get_data_selector(selector) {
                return '[data-drupal-selector="' + selector + '"]';
            }

            function update(values, selector, type) {

                if (type == 'checkboxes') {

                    $(get_data_selector(selector) + ' input.form-checkbox').prop('checked', false);
                    for (var i = 0; i < values.length; i++) {
                        var sel = '[data-drupal-selector="' + selector + '-' + values[i] + '"]';
                        $(sel).prop('checked', true);
                    }

                } else if (type == 'checkbox') {

                    if (values) {
                        $(get_data_selector(selector)).prop('checked', true);
                    }
                    else {
                        $(get_data_selector(selector)).prop('checked', false);
                    }

                } else if (type == 'select') {
                    if (values.length) {
                        $(get_data_selector(selector)).val(values);
                    } else {
                        $(get_data_selector(selector)).val(['_none']);
                    }

                } else {

                    $(get_data_selector(selector)).val(values);

                }

            }
        }
    };

})(jQuery, Drupal, _);
