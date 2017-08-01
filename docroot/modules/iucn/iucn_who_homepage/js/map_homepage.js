/**
 * This function is called after the Google Maps map has been created
 * @param instance_id Instance index where there's more than one map in page
 * @param map Google Maps map object
 */
function postInitMap(instance_id, map, config) {
  (function ($, Drupal, drupalSettings) {
    'use strict';
    var icon1 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'gold',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'gold',
      strokeWeight: 10
    };
    var icon2 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'green',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'green',
      strokeWeight: 10
    };
    var icon3 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'red',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'red',
      strokeWeight: 10
    };
    var icon4 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'blue',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'blue',
      strokeWeight: 10
    };
    var icon5 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'black',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'black',
      strokeWeight: 10
    };
    var icon6 = {
      path: 'M 125,5 155,90 245,90 175,145 200,230 125,180 50,230 75,145 5,90 95,90 z',
      fillColor: 'orange',
      fillOpacity: 1,
      scale: 0.01,
      strokeColor: 'orange',
      strokeWeight: 10
    };
    var $markers = [];
    for(var $i = 0; $i < config.markers.length; $i++) {
      var $mc = config.markers[$i];
      var $marker = new google.maps.Marker({
        map: map,
        position: new google.maps.LatLng($mc.lat, $mc.lng),
        icon: eval('icon' + $mc.status_id),
        customInfo: $mc
      });
      $marker.addListener('click', function() {
        $('#map-site-details').html(this.customInfo.render);
      });
      $markers.push($marker);
    }


    $('#map-filters a').on('click', function() {
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
