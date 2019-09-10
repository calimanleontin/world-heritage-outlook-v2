(function ($, Drupal, _) {

  'use strict';

  Drupal.behaviors.addBodyClassOnOpenDialog = {
    attach: function (context) {
      $("#drupal-modal")
        .on( "dialogopen", function( event, ui ) {
          $('body').addClass('modal-open');
        })
        .on( "dialogclose", function( event, ui ) {
          $('body').removeClass('modal-open');
        });
    }
  }

  Drupal.behaviors.markFullyChangedDiffFields = {
    attach: function (context) {
      var diffContext = $(context).find('.diff-context');
      diffContext.once('setDiffFullBg').each(function () {
        if ($(this).find('.diffchange').length == 0) {
          $(this).addClass('diff-full-bg');
        }
      })
    }
  }

  Drupal.behaviors.fixParagraphResize = {
    attach: function (context) {
      // Workaround for Chrome breaking long paragraph summary components on resize.
      $(window).resize(function () {
        $('.paragraph-summary-component', context).each(function () {
          $(this).css('overflow', 'auto');
          setTimeout(function (element) {
            element.css('overflow', 'hidden');
          }, 0, $(this));
        });
      });
    }
  }

})(jQuery, Drupal, _);
