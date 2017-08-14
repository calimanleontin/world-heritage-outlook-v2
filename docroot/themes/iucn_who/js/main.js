(function($) {
  $(function() {
    var $body = $(document.body);

    var blockBodyScroll = function () {
      $body.addClass('menu-open');
    };
    var unblockBodyScroll = function () {
      $body.removeClass('menu-open');
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

    if (typeof VHChromeFix !== 'undefined') {
        var vhFixOptions = [
            {
                selector: '.path-frontpage #block-homepagemap .row.map-block-main', // Mandatory, CSS selector
                vh: 100,  // Mandatory, height in vh units
            },
        ];
        var vhFix = new VHChromeFix(vhFixOptions);
    }
  });

}(jQuery));
