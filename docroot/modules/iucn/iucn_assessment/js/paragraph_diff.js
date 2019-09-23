/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */
(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.paragraphDiff = {
    attach: function (context) {
      $('a.diff-button').on('click', function () {
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
        return '.diff-modal [data-drupal-selector="' + selector + '"]';
      }

      function update(values, selector, type) {
        if (type == 'checkboxes') {
          if (typeof values === 'number' || typeof values === 'string') {
            values = [values];
          }

          $(get_data_selector(selector) + ' input.form-checkbox').prop('checked', false);
          for (var i = 0; i < values.length; i++) {
            var sel = '[data-drupal-selector="' + selector + '-' + values[i] + '"]';
            var related_selectbox = jQuery(sel).data('drupal-states');
            if (related_selectbox) {
              var visible_keys = Object.keys(related_selectbox.visible);
              for (var j = 0; j < visible_keys.length; j++) {
                var visible_value = related_selectbox['visible'][visible_keys[j]];
                if (visible_value.value != jQuery(visible_keys[j]).val()) {
                  jQuery(visible_keys[j]).val(visible_value.value);
                  jQuery(visible_keys[j]).trigger("chosen:updated");
                  jQuery(visible_keys[j]).trigger('change');
                }
              }
            }
            $(sel).prop('checked', true);
          }
        }
        else if (type == 'checkbox') {
          $(get_data_selector(selector)).prop('checked', values == true);
        }
        else if (type == 'select') {
          if (values.length) {
            $(get_data_selector(selector)).val(values);
          } else {
            $(get_data_selector(selector)).val(['_none']);
          }
          $(get_data_selector(selector)).trigger('change');
        }
        else if (type == 'textarea') {
          $(get_data_selector(selector)).val(values);
          $(get_data_selector(selector)).trigger('change');
        } else if (type == 'text_format') {
          if (values === false) {
            values = '';
          }

          CKEDITOR.instances[$(get_data_selector(selector)).attr('id')].setData(values);
        }
        else {
          $(get_data_selector(selector)).val(values);
        }

      }
    }
  };

})(jQuery, Drupal, _);
