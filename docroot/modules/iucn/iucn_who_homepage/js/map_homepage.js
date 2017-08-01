/**
 * This function is called after the Google Maps map has been created
 * @param instance_id Instance index where there's more than one map in page
 * @param map Google Maps map object
 */
function postInitMap(instance_id, map, config) {
  (function ($, Drupal, drupalSettings) {
    'use strict';
    var uluru = {lat: -25.363, lng: 131.044};
    console.log(config)
    for(var $i = 0; $i < config.markers.length; $i++) {
      var $mc = config.markers[$i];
      console.log($mc);
      var $marker = new google.maps.Marker({
        map: map,
        animation: google.maps.Animation.DROP,
        position: new google.maps.LatLng($mc.lat, $mc.lng)
      });
      // marker.addListener('click', toggleBounce);
    }
  })(jQuery, Drupal, drupalSettings);
}
