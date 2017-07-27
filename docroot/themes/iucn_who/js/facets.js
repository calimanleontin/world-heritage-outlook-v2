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
  });
})(jQuery);
