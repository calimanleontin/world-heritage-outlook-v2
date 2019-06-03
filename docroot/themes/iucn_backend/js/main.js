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
      if(diffContext.find('.diffchange').length == 0) {
        diffContext.addClass('diff-full-bg');
      }
    }
  }

})(jQuery, Drupal, _);
