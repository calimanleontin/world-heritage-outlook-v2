/**
 * @file
 * CKEditor height.
 */

/**
 * Decorates ckeditor.attach
 */
Drupal.editors.ckeditor.attach = function(method, drupalSettings, CKEDITOR, $) {
  return function (element, format)
  {
    var toPx = function(value) {
      var element = jQuery('<div style="display: none; font-size: ' + value + '; margin: 0; padding:0; height: auto; line-height: 1; border:0;">&nbsp;</div>').appendTo('body');
      var height = element.height();
      element.remove();
      return height;
    };

    var rows = element.getAttribute("rows");
    if (rows) {
      // Calculate heightInPx.
      drupalSettings.ckeditorheight = drupalSettings.ckeditorheight || {};
      var offset = drupalSettings.ckeditorheight.offset || 1;
      var line_height = drupalSettings.ckeditorheight.line_height || 1.5;
      var unit = drupalSettings.ckeditorheight.unit || 'em';
      var disable_autogrow = drupalSettings.ckeditorheight.disable_autogrow || false;

      var height = (rows * line_height + offset);
      var heightWithUnit = height + unit;
      var heightInPx = toPx(heightWithUnit);

      // Override the height.
      var editorSettingsOverrides = {
        height: heightInPx,
        autoGrow_minHeight: heightInPx
      };
      if (disable_autogrow) {
        editorSettingsOverrides.autoGrow_maxHeight = heightInPx;
      }
      var overriddenEditorSettings = $.extend({}, format.editorSettings, editorSettingsOverrides);
      var overriddenFormat = $.extend({}, format, {editorSettings: overriddenEditorSettings});
    }
    return method.apply(this, [element, overriddenFormat]);
  }
}(Drupal.editors.ckeditor.attach, drupalSettings, CKEDITOR, jQuery);
