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
    var $window = $(window);
    var $footer = $('.footer');
    var $sidemenuToggle = $('#sidemenu-toggle');

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
  });
})(jQuery);
