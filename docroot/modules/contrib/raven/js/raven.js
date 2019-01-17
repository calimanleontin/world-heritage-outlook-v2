/**
 * @file
 * Configures @sentry/browser with the public DSN and extra options.
 */
(function (drupalSettings, Sentry) {

  'use strict';

  Sentry.init(drupalSettings.raven.options);

})(window.drupalSettings, window.Sentry);
