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
      config.icons[$i].scaledSize = new google.maps.Size(24, 24);
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
      // Click on marker
      $marker.addListener('click', function() {
        $.iucnResetAllMarkerIcons();
        var $icon = config.icons['icon' + this.customInfo.status_id + 'Active'];
        this.setIcon($icon);
        $('#map-site-details').html(this.customInfo.render);
      });
      $markers.push($marker);
    }

    $.iucnResetAllMarkerIcons = function() {
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $icon = config.icons['icon' + $marker.customInfo.status_id];
        // Avoid some suble flickering
        if ($icon.url != $marker.getIcon().url) {
          $marker.setIcon($icon);
        }
      }
    };
    $.iucnClearSelection = function() {
      $.iucnResetAllMarkerIcons();
      $('#map-site-details').html(config.empty_placeholder);
    }

    // Click on any of the filters
    $('#map-filters a').on('click', function() {
      $('#map-filters li').removeClass('active');
      $(this).parent().addClass('active');
      var $filter_status_id = $(this).data('filter');
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $visible = $filter_status_id == 'all'
            || parseInt($filter_status_id) == parseInt($marker.customInfo.status_id);
        $marker.setVisible($visible);
      }
      if ($filter_status_id == 'all') {
        map.setZoom(parseInt(config.map_init_zoom));
        map.setCenter(
            new google.maps.LatLng(
                parseFloat(config.map_init_lat),
                parseFloat(config.map_init_lng)
            )
        );
      }
      else {
        // Clear selection since this site might not exist in new filtering
        $.iucnClearSelection();
      }
      return false;
    });

    // Search autocomplete selection
    $(document).on('autocompleteclose', '.form-autocomplete', function (e) {
      var $value = e.target.value;
      if ($value.length > 0) {
        var $found = false;
        for(var $i = 0; $i < $markers.length; $i++) {
          var $marker = $markers[$i];
          var $visible = $marker.customInfo.title == $value;
          $marker.setVisible($visible);
          if (!$found && $visible) {
            $found = true;
            $('#map-site-details').html($marker.customInfo.render);
            map.setCenter($marker.getPosition());
            map.setZoom(7); // @todo variable
          }
        }
        e.stopPropagation();
        e.preventDefault();
      }
    });
  })(jQuery, Drupal, drupalSettings);
}

function homepageMapSiteDetailClose() {
  (function ($) {
    'use strict';
    $.iucnClearSelection();
  })(jQuery, Drupal, drupalSettings);
}
