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
        title: $mc.title,
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

    var $called = false;
    google.maps.event.addListener(map, 'bounds_changed', function() {
      if ($called == false) {
        var $bounds = map.getBounds();
        // console.log($markers);
        for (var $i = 0; $i < $markers.length; $i++) {
          var $marker = $markers[$i];
          $bounds.extend($marker.getPosition());
        }
        // map.fitBounds($bounds);
        $called = true;
      }
    });

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

    $.showAllMarkers = function(show) {
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        $marker.setVisible(show);
      }
    };

    /**
     * Clear currently selected marker and infoWindow content.
     */
    $.iucnClearSelection = function() {
      $.iucnResetAllMarkerIcons();
      $('#edit-q').prop('selectedIndex', 0);
      $('#edit-q').trigger('chosen:updated');
      $('#map-site-details').html(config.empty_placeholder);
    };


    /**
     * Zoom and pan the map to its original position.
     */
    $.resetMapPosition = function() {
      map.setZoom(parseInt(config.map_init_zoom));
      map.setCenter(
          new google.maps.LatLng(
              parseFloat(config.map_init_lat),
              parseFloat(config.map_init_lng)
          )
      );
    };


    /**
     * Click handler for filters on the left column.
     */
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
        $.iucnClearSelection();
        $.resetMapPosition();
      }
      else {
        // Clear selection since this site might not exist in new filtering
        $.iucnResetAllMarkerIcons();
      }
      return false;
    });

    /**
     * Handle the site selection from the drop-down.
     */
    $('#edit-q').on('change', function() {
      if ($(this).val() !== '') {
        for (var $i = 0; $i < $markers.length; $i++) {
          var $marker = $markers[$i];
          if ($marker.customInfo.id == $(this).val()) {
            $('#map-site-details').html($marker.customInfo.render);
            var $icon = config.icons['icon' + $marker.customInfo.status_id + 'Active'];
          }
          else {
            var $icon = config.icons['icon' + $marker.customInfo.status_id];
          }
          $marker.setIcon($icon);
        }
      }
      else {
        $.resetMapPosition();
        $.iucnResetAllMarkerIcons();
        $.showAllMarkers(true);
      }
    });
  })(jQuery, Drupal, drupalSettings);
}

function homepageMapSiteDetailClose() {
  (function ($) {
    'use strict';
    $.resetMapPosition();
    $.iucnClearSelection();
  })(jQuery, Drupal, drupalSettings);
}
