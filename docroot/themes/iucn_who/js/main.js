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

    // Shows outlines if navigating with keyboard
    document.addEventListener('keydown', function(e) {
        if (e.keyCode === 9) {
        $body.addClass('show-focus-outlines');
        }
    });
    document.addEventListener('touchstart', function() {
        $body.removeClass('show-focus-outlines');
    });
    document.addEventListener('mousedown', function() {
        $body.removeClass('show-focus-outlines');
    });
  });

}(jQuery));
