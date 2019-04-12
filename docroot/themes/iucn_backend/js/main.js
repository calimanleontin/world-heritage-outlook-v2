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

})(jQuery, Drupal, _);
