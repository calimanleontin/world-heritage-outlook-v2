// noinspection JSUnusedGlobalSymbols
function initMap() {
  var $maps = document.getElementsByClassName('map-container');
  var $map_styles = [];
  $map_styles['default'] = [];
  $map_styles['grayscale'] = [{'featureType': 'landscape','stylers': [{'color': '#f5f5f5'}]},{'featureType': 'water','stylers': [{'color': '#c9c9c9' }]}];
  for(var $i = 0; $i < $maps.length; $i++) {
    var $element = $maps[$i];
    var $instance_id = $element.getAttribute('data-instance');
    var $config = drupalSettings['GoogleMapsBaseBlock'][$instance_id];
    var $center = new google.maps.LatLng(parseFloat($config.map_init_lat), parseFloat($config.map_init_lng));
    var $zoom = parseInt($config.map_init_zoom);
    var $map = new google.maps.Map(document.getElementById($element.id), {
      zoom: $zoom,
      center: {
        lat: 0,
        lng: 0
      },
      mapTypeId: $config.map_init_type,
      styles: $map_styles[$config.map_style],
    });

    // Save the map in the config scope
    $config.map = $map;
    postInitMap($instance_id, $map, $config);
  }
}
