Raven Sentry client
===================

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers


INTRODUCTION
------------

Raven module is a Drupal client for [Sentry](https://sentry.io/), an open source
exception logging, aggregation and notification platform.

This module allows your Drupal site to send errors, warnings and notices to a
Sentry server, including fatal PHP and JavaScript errors that typically are not
logged by Drupal.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/raven

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/raven


REQUIREMENTS
------------

Dependencies are defined in the composer.json file.


INSTALLATION
------------

Run `composer require drupal/raven` to install this module and its dependencies.

You can use the Sentry hosted service or install the Sentry app on your own
infrastructure (e.g. using Docker).


CONFIGURATION
-------------

This module logs errors to Sentry in a few ways:

 * Register a Drupal logger implementation (for uncaught exceptions, PHP errors,
   and Drupal log messages),
 * Record Sentry breadcrumbs for system events,
 * Register an error handler for fatal errors, and
 * Handle JavaScript exceptions via Sentry browser client (if user has the "Send
   JavaScript errors to Sentry" permission).

You can choose which events you want to capture by visiting the Raven
configuration page at admin/config/development/logging and enabling desired
error handlers and selecting error levels.

Additional customizations can be performed by implementing hooks:

 * `hook_raven_options_alter()`: Modify the Raven client configuration.
 * `hook_raven_filter_alter()`: Modify or ignore Drupal log events.
 * `hook_raven_breadcrumb_alter()`: Modify or ignore Sentry breadcrumbs.

The logger records a Sentry breadcrumb for each log message. If sensitive debug
data is recorded in a breadcrumb, and a log message is later captured by Sentry,
this could result in sending sensitive data to the Sentry server. To modify or
suppress breadcrumbs, you may implement hook_raven_breadcrumb_alter().

The Sentry browser client configuration can be modified via the
`$page['#attached']['drupalSettings']['raven']['options']` object in PHP or the
`drupalSettings.raven.options` object in JavaScript.


TROUBLESHOOTING
---------------

If the client is configured incorrectly (e.g. wrong Sentry DSN) it should fail
silently. Sentry browser client may log an error in the browser console.

If you have code that generates thousands of PHP notices - for example,
processing a large set of data, with one notice for each item - you may find
that storing and sending the errors to Sentry requires a large amount of memory
and execution time, enough to exceed your configured `memory_limit` and
`max_execution_time` settings. This could result in a stalled or failed request.
A workaround for this case would be to disable sending notice-level events to
Sentry.


DRUSH INTEGRATION
-----------------

The `drush raven:captureMessage` command sends a message to Sentry.


MAINTAINERS
-----------

This module is not affiliated with Sentry. It was originally created by
[nodge](https://www.drupal.org/u/nodge) and is now developed by
[mfb](https://www.drupal.org/u/mfb).

Maintenance of this module is supported by the Electronic Frontier Foundation.

 * Build status: https://www.drupal.org/node/2599354/qa
