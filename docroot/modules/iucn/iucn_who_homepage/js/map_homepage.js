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
      config.icons[$i].scaledSize = new google.maps.Size(12, 12);
    }
    var $markers = [];
    for(var $i = 0; $i < config.markers.length; $i++) {
      var $mc = config.markers[$i];
      var $marker = new google.maps.Marker({
        map: map,
        position: new google.maps.LatLng($mc.lat, $mc.lng),
        icon: config.icons['icon' + $mc.status_id],
        customInfo: $mc
      });
      $marker.addListener('click', function() {
        $('#map-site-details').html(this.customInfo.render);
      });
      $markers.push($marker);
    }

    // Filters
    $('#map-filters a').on('click', function() {
      $('#map-filters li').removeClass('active');
      $(this).parent().addClass('active');
      var $filter_status_id = $(this).data('filter');
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $visible =  $filter_status_id == 'all'
            || parseInt($filter_status_id) == parseInt($marker.customInfo.status_id);
        $marker.setVisible($visible);
      }
      return false;
    });
  })(jQuery, Drupal, drupalSettings);
}

function homepageMapSiteDetailClose() {
  (function ($) {
    'use strict';
    $('#map-site-details').html('');
  })(jQuery, Drupal, drupalSettings);
}
