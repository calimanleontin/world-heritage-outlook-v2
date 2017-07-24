(function($) {
  $(function() {
    var $body = $(document.body);

    var blockBodyScroll = function () {
      $body.addClass('modal-open');
    };
    var unblockBodyScroll = function () {
      $body.removeClass('modal-open');
    };

    $('#navbar-collapse').on('show.bs.collapse', blockBodyScroll);
    $('#navbar-collapse').on('hide.bs.collapse', unblockBodyScroll);
  });

}(jQuery));
