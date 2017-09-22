// TODO if we change google_maps_api - we should do this with Drupal behaviours.
// right now a function initMap is called from it :(
function initMap() {
  if (typeof drupalSettings.siteAreaMap == 'undefined') {
    return;
  }
  var maps = drupalSettings.siteAreaMap;
  jQuery.each(maps, function(idx, elem) {

    var center = new google.maps.LatLng(parseFloat(elem.markers[0].lat),
        parseFloat(elem.markers[0].lng));

    var map = new google.maps.Map(document.getElementById(elem.mapid), {
      zoom: 7,
      center: center,
      // mapTypeId: elem.map_type,
      styles: siteFormatterMapStyle
    });

    var bounds = new google.maps.LatLngBounds();
    map.data.addListener('addfeature', function(e) {
      siteProcessPoints(e.feature.getGeometry(), bounds.extend, bounds);
      map.fitBounds(bounds);
    });

    jQuery.each(elem.markers, function(idx, marker) {
      if (marker.hasOwnProperty('lat')) {
        new google.maps.Marker({
          title: marker.title,
          map: map,
          position: new google.maps.LatLng(marker.lat, marker.lng),
          icon: marker.icon
        });
      }
      if (marker.hasOwnProperty('area')) {
        map.data.loadGeoJson(marker.area);
        map.data.setStyle({
          fillColor: marker.area_color,
          strokeColor: marker.area_color,
          strokeWeight: 1
        });
      }
    });
  });
}

function siteProcessPoints(geometry, callback, thisArg) {
  if (geometry instanceof google.maps.LatLng) {
    callback.call(thisArg, geometry);
  } else if (geometry instanceof google.maps.Data.Point) {
    callback.call(thisArg, geometry.get());
  } else {
    geometry.getArray().forEach(function(g) {
      siteProcessPoints(g, callback, thisArg);
    });
  }
}
