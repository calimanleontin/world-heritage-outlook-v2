!function(exports, $, undefined) {
  'use strict';

  var IUCNSidemenu = function() {
    var self = this;
    var $ = jQuery;
    this.body = $('body');
    this.document  = $(document);
    this.modalBackdrop = $('.modal-backdrop');
    this.sidemenu = $('#sidemenu');
    this.sidemenuToggle = $('#sidemenu-toggle');

  };

  IUCNSidemenu.prototype.openMenu = function () {
    this.sidemenuToggle.dropdown('toggle');
    this.body.addClass('sidemenu-open');
    this.modalBackdrop.show().addClass('in');
  };

  IUCNSidemenu.prototype.closeMenu =  function () {
    this.sidemenuToggle.dropdown('toggle');
    this.body.removeClass('sidemenu-open');
    this.modalBackdrop.hide().removeClass('in');
  };

  exports.IUCNSidemenu = IUCNSidemenu;

}(this, jQuery);
