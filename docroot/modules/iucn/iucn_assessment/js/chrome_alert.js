/**
 * @file
 * Javascript alert for other browsers than chrome
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.chromeAlert = {
    attach: function (context) {
      var user_agent = navigator.userAgent.toLowerCase();
      if (user_agent.indexOf('chrome') > -1) {
        return;
      }

      var message = '<h6>This interface is not optimized for your browser. Please use Chrome or Firefox!</h6>';
      if (user_agent.indexOf('firefox') > -1) {
        message = '<h6>This page is not optimized for Firefox browser. If you encounter any errors, please use Chrome!</h6>';
      }

      var cookieName = 'site_assessment_chrome_firefox_alert';
      if (jQuery.cookie(cookieName)) {
        return;
      }

      var title = 'Warning!';

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
