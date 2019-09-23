/**
 * @file
 * Javascript functionality for the row paragraphs widget.
 */

(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.paragraphComments = {
    attach: function (context) {

      var selected = $(context).find('li.horizontal-tab-button.selected');
      var comments_textarea = $(context).find('.paragraph-comments-textarea');

      if (comments_textarea.length === 0) {
        return;
      }

      var comment_dots;
      selected.once('commentBubbles').each(function () {
        var selected_html = selected.html();
        selected_html += '<i class="fa fa-comment-dots"></i>';
        selected.html(selected_html);
        comment_dots = $(context).find(".fa-comment-dots");

        function deselect(e) {
          comments_textarea.slideFadeToggle(function() {
            e.removeClass('selected');
          });
        }

        function moveRelativeTo(a, b) {
          var destination = b.offset();
          var document_width = $(window).width();
          var comments_location;
          if (destination.left + 25 > document_width / 2) {
            comments_location = {top: destination.top + 25, right: document_width - destination.left - 25, left: 'initial'};
          }
          else {
            comments_location = {top: destination.top + 25, left: destination.left, right: 'initial'};
          }
          a.css(comments_location);
        }

        $.fn.slideFadeToggle = function(easing, callback) {
          return this.animate({ opacity: 'toggle', height: 'toggle' }, 'fast', easing, callback).css('display', 'inline-block');
        };

        comment_dots.click(function (e) {
          e.preventDefault();
          if (!$(this).hasClass('selected')) {
            $(this).addClass('selected');
            comments_textarea.slideFadeToggle();
            moveRelativeTo(comments_textarea, $(this));
          }
        });

        $(document).mouseup(function(e) {
          // if the target of the click isn't the container nor a descendant of the container
          if (!comments_textarea.is(e.target) && comments_textarea.has(e.target).length === 0) {
            if (comment_dots.hasClass('selected')) {
              deselect(comment_dots);
            }
          }
        });
      });
    }
  };

})(jQuery, Drupal, _);
