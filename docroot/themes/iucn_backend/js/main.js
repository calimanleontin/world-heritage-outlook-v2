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

  Drupal.behaviors.diffBorder = {
    attach: function (context) {
      var diffFinal = $(context).find('.initial-row + .final-row');
      diffFinal.once('setDiffBorder').each(function () {
        $(this).before('<tr class="diff-separator"><td colspan="10"><div style="border-top: 1px dashed black; margin-bottom: 5px;"></div></td></tr>');
      });
    }
  }

})(jQuery, Drupal, _);
