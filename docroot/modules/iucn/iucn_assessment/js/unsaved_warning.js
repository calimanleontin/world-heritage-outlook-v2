/**
 * @file
 * Javascript Warning leaving the form unsaved
 */

(function($) {

  var ml = ml || {};
  ml.options = ml.options || {};

  Drupal.behaviors.unsaved_form_leaving = {
    attach: function(context) {
      var $context = $(context);
      if (Drupal.ckeditor != undefined) {
        ml.ckeditor();
      }
      // Chrome shows own text for beforeunload 'Changes you made may not be saved.'
      window.addEventListener("beforeunload", function (e) {
        var submitButtonClicked = (e.target.activeElement.type === "submit");
        var formIsModified = $("form.node-form").data("changed");
        var orderIsCHanged = $('form.node-form').has('abbr.warning.tabledrag-changed').length !== 0;
        if ((orderIsCHanged || formIsModified) && !submitButtonClicked) {
          // Cancel the event
          e.preventDefault();
          // Chrome requires returnValue to be set
          e.returnValue = '';
        }
        // Do something
      }, false);
    },
    detach: function(context, settings) {
      var $context = $(context);
    }
  };

  /**
   * Integrate with ckEditor
   * Detect changes on editors
   */
  ml.ckeditor = function() {
    // Since Drupal.attachBehaviors() can be called more than once, and
    // ml.ckeditor() is being called in maxlength behavior, only run this once.
    if (!ml.ckeditorOnce) {
      ml.ckeditorOnce = true;
      CKEDITOR.on('instanceReady', function(e) {
        var editorSelector = '#' + e.editor.name + '.maxlength';
        if ($("form.node-form").has(editorSelector).length === 0) {
          return;
        }

        var editor = $(editorSelector);

        if (editor.length == 1) {
          e.editor.on('key', function(e) {
            $("form.node-form").data("changed", true);
          });
          e.editor.on('paste', function(e) {
            $("form.node-form").data("changed", true);
          });
        }
      });
    }
  }
})(jQuery);
