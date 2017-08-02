// noinspection JSUnusedGlobalSymbols
function initMap() {
  var $maps = document.getElementsByClassName('map-container');
  for(var $i = 0; $i < $maps.length; $i++) {
    var $element = $maps[$i];
    var $instance_id = $element.getAttribute('data-instance');
    var $config = drupalSettings['GoogleMapsBaseBlock'][$instance_id];
    var $map = new google.maps.Map(document.getElementById($element.id), {
      zoom: parseInt($config.map_init_zoom),
      center: new google.maps.LatLng(parseFloat($config.map_init_lat), parseFloat($config.map_init_lng)),
      mapTypeId: $config.map_init_type
    });
    // Save the map in the config scope
    $config.map = $map;
    postInitMap($instance_id, $map, $config);
  }
}
