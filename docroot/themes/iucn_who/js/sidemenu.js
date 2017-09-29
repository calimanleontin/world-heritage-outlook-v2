!function(exports, $, Drupal, undefined) {
  'use strict';

  var IUCNSidemenu = function() {
    var self = this;
    var $document  = $(document);
    var $body  = $('body');

    this.modalBackdrop = $('.modal-backdrop').addClass('fade');
    this.sidemenu = $('#sidemenu');
    this.sidemenuToggle = $('#sidemenu-toggle');
    this.closeButton = this.sidemenu.find('.close');

    if(!this.closeButton.length) {
      this.closeButton = $('<button type="button" class="close sidemenu-close" aria-label="' + Drupal.t('Close') + '"><span aria-hidden="true">&times;</span></button>');
      this.closeButton.on('click', function () {
        self.closeMenu();
      });
      this.sidemenu.prepend(this.closeButton);
    }

    if(!this.modalBackdrop.length) {
      $body.append($('<div class="modal-backdrop fade"></div>'));
    }

    this.sidemenu.on('show.bs.dropdown', function () {
      $body.addClass('sidemenu-open');
      self.modalBackdrop.addClass('in');
    })
    this.sidemenu.on('hide.bs.dropdown', function () {
      $body.removeClass('sidemenu-open');
      self.modalBackdrop.removeClass('in');
    })

    $document.on('click', '#sidemenu', function (e) {
      e.stopPropagation();
    });

  };

  IUCNSidemenu.prototype.openMenu = function () {
    this.sidemenuToggle.dropdown('toggle');
    $('body').addClass('sidemenu-open');
    this.modalBackdrop.addClass('in');
  };

  IUCNSidemenu.prototype.closeMenu =  function () {
    this.sidemenuToggle.dropdown('toggle');
    $('body').removeClass('sidemenu-open');
    this.modalBackdrop.removeClass('in');
  };

  exports.IUCNSidemenu = IUCNSidemenu;

}(this, window.jQuery, window.Drupal);
