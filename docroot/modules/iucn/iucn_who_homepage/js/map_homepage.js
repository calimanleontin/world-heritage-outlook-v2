/**
 * This function is called after the Google Maps map has been created
 * @param instance_id Instance index where there's more than one map in page
 * @param map Google Maps map object
 */
function postInitMap(instance_id, map, config) {
  (function ($, Drupal, drupalSettings) {
    'use strict';
    for(var $i = 0; $i < config.markers.length; $i++) {
      var $mc = config.markers[$i];
      var $marker = new google.maps.Marker({
        map: map,
        animation: google.maps.Animation.DROP,
        position: new google.maps.LatLng($mc.lat, $mc.lng),
        customInfo: $mc
      });
      $marker.addListener('click', function() {
        $('#map-site-details').html(this.customInfo.render);
      });
    }
  })(jQuery, Drupal, drupalSettings);
}

function homepageMapSiteDetailClose() {
  (function ($) {
    'use strict';
    $('#map-site-details').html('');
  })(jQuery, Drupal, drupalSettings);
}
