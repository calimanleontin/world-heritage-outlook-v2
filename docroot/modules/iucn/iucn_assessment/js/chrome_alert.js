/**
 * @file
 * Javascript alert for other browsers than chrome
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.chromeAlert = {
    attach: function (context) {
      var isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);

      if (isChrome) {
        return;
      }

      var cookieName = 'site_assessment_chrome_alert';
      if (jQuery.cookie(cookieName)) {
        return;
      }

      var title = 'Warning!';
      var message = '<h6>This interface is not optimized for your browser. Please use Chrome!</h6>';

      $('<div></div>').appendTo('body')
        .html(message)
        .dialog({
          modal: true, title: title, zIndex: 10000, autoOpen: true,
          width: 'auto', resizable: false,
          buttons: {
            Ok: function () {
              $(this).dialog("close");
            },
          },
          close: function (event, ui) {
            jQuery.cookie(cookieName, true, {
              expires: 1,
              path: '/'
            });
            $(this).remove();
          }
        });
    },
  };

})(jQuery, Drupal, _);
