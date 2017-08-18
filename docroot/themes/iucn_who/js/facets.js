;(function ($) {
  'use strict';

  $(function() {
    var facetCollapse = $('.region-facets .collapse');

    if(facetCollapse.length) {
        facetCollapse.each(function () {
            var self = $(this);
            var actives = self.find('.is-active');
            if(actives.length) {
                self.addClass('in');
                var toggle = $('[aria-controls="' + self[0].id + '"]');
                toggle
                    .addClass('active-facet')
                    .removeClass('collapsed')
                    .attr({
                        'aria-expanded': 'true'
                    });
            }
        });
    }
    var $document  = $(document);
    var $window = $(window);
    var $body = $('body');
    var $footer = $('.footer');
    var $sidemenu = $('#sidemenu');
    var $sidemenuToggle = $('#sidemenu-toggle');
    var $modalBackdrop = $('.modal-backdrop');

    setTimeout( function() {
        $sidemenuToggle.affix({
          offset: {
            bottom: function () {
              return (this.bottom = $footer.outerHeight(true))
            }
          }
        });
    }, 100 );

    $window.resize(function() {
        if ($sidemenuToggle.data('bs.affix') !== undefined) {
          $sidemenuToggle.data('bs.affix').options.offset.top = $footer.outerHeight(true);
        }
    });

    $sidemenu.on('show.bs.dropdown', function () {
      $body.addClass('sidemenu-open');
      $modalBackdrop.show().addClass('in');
    })
    $sidemenu.on('hide.bs.dropdown', function () {
      $body.removeClass('sidemenu-open');
      $modalBackdrop.hide().removeClass('in');
    })

    $document.on('click', '#sidemenu', function (e) {
      e.stopPropagation();
    });
  });
})(jQuery);
