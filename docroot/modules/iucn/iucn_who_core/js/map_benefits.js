/**
 * This function is called after the Google Maps map has been created
 * @param instance_id Instance index where there's more than one map in page
 * @param map Google Maps map object
 */
function postInitMap(instance_id, map, config) {
  (function ($, Drupal, drupalSettings) {
    'use strict';
    // Scale-down the images a bit
    for (var $i in config.icons) {
      config.icons[$i].scaledSize = new google.maps.Size(42, 50);
    }
    config.icons['all'].scaledSize = new google.maps.Size(33, 33);
    config.icons['all_active'].scaledSize = new google.maps.Size(33, 33);

    var $filter_category = 'all';

    var $markers = [];
    for(var $i = 0; $i < config.markers.length; $i++) {
      var $mc = config.markers[$i];
      var $marker = new google.maps.Marker({
        title: $mc.title,
        map: map,
        position: new google.maps.LatLng($mc.lat, $mc.lng),
        icon: config.icons.all,
        customInfo: $mc
      });
      // Click on marker
      $marker.addListener('click', function() {
        $.iucnResetAllMarkerIcons();
        if($filter_category == 'all'){
          var $icon = config.icons[$filter_category + '_active'];
          this.setIcon($icon);
        }

        $.iucnUpdateMapDetail(this.customInfo.render);
      });
      $markers.push($marker);
    }





      /**
     * Click handler for filters on the left column.
     */
    $('a.benefit-category').on('click', function() {
      $('.benefit-category-container').removeClass('active');
      $(this).closest('.benefit-category-container').addClass('active');
      $filter_category = $(this).data('category');
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $visible = $filter_category == 'all'
            || ($.iucnCheckIfExistingValue($marker.customInfo.benefits, $filter_category));
        if($visible){
          $markers[$i].icon = config.icons[$filter_category];
        }
        $marker.setVisible($visible);
      }

      var bounds = new google.maps.LatLngBounds();
      $.each($markers, function(idx, elem){
        if (elem.getVisible()) {
          bounds.extend(elem.position);
        }
      });

      map.fitBounds(bounds);
      if (map.zoom == 1) {
        $.resetMapPosition();
      }

      $.iucnResetMapDetail();
      $.iucnResetAllMarkerIcons();
      return false;
    });

    $.iucnCheckIfExistingValue = function(obj, key) {
      return obj.hasOwnProperty(key);
    }

    $.iucnResetAllMarkerIcons = function() {
      for(var $i = 0; $i < $markers.length; $i++) {
        var $active_markers = [];
        var $marker = $markers[$i];
        var $icon = config.icons[$filter_category];
          if ($icon.url != $marker.getIcon().url) {
            $marker.setIcon($icon);
            $active_markers[$i] = $marker;
          }
      }
    };

    $.iucnUpdateMapDetail = function(mapDetail) {
      var $mapDetails = $('#map-site-details');
      $mapDetails.fadeOut(0).html(mapDetail).fadeIn(300);
    };


    /**
     * Zoom and pan the map to its original position.
     */
    $.resetMapPosition = function() {
      var $config = drupalSettings['GoogleMapsBaseBlock'][1];
      var $center = new google.maps.LatLng(parseFloat($config.map_init_lat), parseFloat($config.map_init_lng));
      var $zoom = parseInt($config.map_init_zoom);

      map.setZoom($zoom);
      map.setCenter($center);
    };

    $.iucnResetMapDetail = function() {
      var $mapDetails = $('#map-site-details');
      $mapDetails.fadeOut(0).html(config.empty_placeholder).fadeIn(300);
    };

  })(jQuery, Drupal, drupalSettings);
}

function benefitsMapSiteDetailClose() {
  (function ($) {
    'use strict';
    $.iucnResetAllMarkerIcons();
    $.iucnResetMapDetail();
  })(jQuery);
}

function benefitsCategoriesExpandAll() {
  (function ($) {
    'use strict';
    $('#map-filters .collapse').collapse('show');

  })(jQuery);
}

function benefitsCategoriesCollapseAll() {
  (function ($) {
    'use strict';
    $('#map-filters .collapse').collapse('hide');
  })(jQuery);
}
