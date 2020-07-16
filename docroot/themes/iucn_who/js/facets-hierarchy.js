(function ($) {
  'use strict';

  Drupal.behaviors.facets_hierarchy = {
    attach: function (context) {
      $('.facets-widget-links>ul>li>span.facet-icon-collapse>span.facet-item__value').once('facetsHierarchy').on('click', function () {
        collapseFacet($(this));
      });

      $('.facets-widget-links>ul>li>span>div.facets-widget->ul').once('initFacetsHierarchy').each(function () {
        if ($(this).find('span.facet-item__status').length > 0) {
          collapseFacet($(this).parent().parent().find('span.facet-item__value'));
        }
      });

      function collapseFacet(facet) {
        facet.parent().find('div.facets-widget->ul').slideToggle();
        facet.parent().toggleClass('collapsed').attr('aria-expanded', !facet.parent().attr('aria-expanded'));
      }

    }
  }
})(jQuery);
