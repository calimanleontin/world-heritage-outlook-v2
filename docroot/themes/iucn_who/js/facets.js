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
    var $fixedBottom = $('#iucn-fixed-bottom');

    setTimeout( function() {
        $fixedBottom.affix({
          offset: {
            bottom: function () {
              return (this.bottom = $footer.outerHeight(true))
            }
          }
        });
    }, 100 );

    $window.resize(function() {
        if ($fixedBottom.data('bs.affix') !== undefined) {
          $fixedBottom.data('bs.affix').options.offset.top = $footer.outerHeight(true);
        }
    });
  });
})(jQuery);
