/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.paragraphComments = {
    attach: function (context) {

      var selected = $(context).find('li.horizontal-tab-button.selected');
      var selected_html = selected.html();
      selected_html += '<i class="fa fa-comment-dots"></i>';
      selected.html(selected_html);

      function deselect(e) {
        $(context).find('.paragraph-comments-textarea').slideFadeToggle(function() {
          e.removeClass('selected');
        });
      }

      $.fn.slideFadeToggle = function(easing, callback) {
        return this.animate({ opacity: 'toggle', height: 'toggle' }, 'fast', easing, callback);
      };

      $(context).find(".fa-comment-dots").click(function (e) {
        e.preventDefault();
        if($(this).hasClass('selected')) {
          deselect($(this));
        }
        else {
          $(this).addClass('selected');
          var textarea = $(context).find('.paragraph-comments-textarea');
          textarea.slideFadeToggle();
          var destination = $(this).offset();
          textarea.css({top: destination.top + 25});
        }
        return false;
      });
    }
  };

})(jQuery, Drupal, _);
