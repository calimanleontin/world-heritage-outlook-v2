// noinspection JSUnusedGlobalSymbols
function initMap() {
  var $maps = document.getElementsByClassName('map-container');
  var $map_styles = [];
  $map_styles['default'] = [];
  $map_styles['grayscale'] = [{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#444444"}]},{"featureType":"landscape","elementType":"all","stylers":[{"color":"#f2f2f2"}]},{"featureType":"poi","elementType":"all","stylers":[{"visibility":"on"}]},{"featureType":"poi","elementType":"geometry.fill","stylers":[{"saturation":"-100"},{"lightness":"57"}]},{"featureType":"poi","elementType":"geometry.stroke","stylers":[{"lightness":"1"}]},{"featureType":"poi","elementType":"labels","stylers":[{"visibility":"off"}]},{"featureType":"road","elementType":"all","stylers":[{"saturation":-100},{"lightness":45}]},{"featureType":"road.highway","elementType":"all","stylers":[{"visibility":"simplified"}]},{"featureType":"road.arterial","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"transit.station.bus","elementType":"all","stylers":[{"visibility":"on"}]},{"featureType":"transit.station.bus","elementType":"labels.text.fill","stylers":[{"saturation":"0"},{"lightness":"0"},{"gamma":"1.00"},{"weight":"1"}]},{"featureType":"transit.station.bus","elementType":"labels.icon","stylers":[{"saturation":"-100"},{"weight":"1"},{"lightness":"0"}]},{"featureType":"transit.station.rail","elementType":"all","stylers":[{"visibility":"on"}]},{"featureType":"transit.station.rail","elementType":"labels.text.fill","stylers":[{"gamma":"1"},{"lightness":"40"}]},{"featureType":"transit.station.rail","elementType":"labels.icon","stylers":[{"saturation":"-100"},{"lightness":"30"}]},{"featureType":"water","elementType":"all","stylers":[{"color":"#d2d2d2"},{"visibility":"on"}]}];
  for(var $i = 0; $i < $maps.length; $i++) {
    var $element = $maps[$i];
    var $instance_id = $element.getAttribute('data-instance');
    var $config = drupalSettings['GoogleMapsBaseBlock'][$instance_id];
    var $center = new google.maps.LatLng(parseFloat($config.map_init_lat), parseFloat($config.map_init_lng));
    var $zoom = parseInt($config.map_init_zoom);
    var $map = new google.maps.Map(document.getElementById($element.id), {
      zoom: $zoom,
      center: $center,
      mapTypeId: $config.map_init_type,
      styles: $map_styles[$config.map_style],
    });
    // Save the map in the config scope
    $config.map = $map;
    postInitMap($instance_id, $map, $config);
  }
}
