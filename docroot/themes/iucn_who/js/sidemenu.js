!function(exports, $, undefined) {
  'use strict';

  var IUCNSidemenu = function() {
    var self = this;
    var $ = jQuery;
    var $document  = $(document);
    var $body  = $('body');

    this.modalBackdrop = $('.modal-backdrop').addClass('fade');
    this.sidemenu = $('#sidemenu');
    this.sidemenuToggle = $('#sidemenu-toggle');

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

}(this, jQuery);
