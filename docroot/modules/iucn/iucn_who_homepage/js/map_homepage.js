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
      config.icons[$i].origin = new google.maps.Point(config.icons[$i].origin_x, config.icons[$i].origin_y);
      config.icons[$i].size = new google.maps.Size(config.icons[$i].width, config.icons[$i].height);
      delete config.icons[$i].origin_x;
      delete config.icons[$i].origin_y;
      delete config.icons[$i].width;
      delete config.icons[$i].height;
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
        $.iucnUpdateMapDetail(this.customInfo.render);
        $('#edit-q').prop('selectedIndex', 0);
        $('#edit-q').trigger('chosen:updated');
      });
      $markers.push($marker);
    }

    var $called = false;
    google.maps.event.addListener(map, 'bounds_changed', function() {
      if ($called == false) {
        var $bounds = map.getBounds();
        for (var $i = 0; $i < $markers.length; $i++) {
          var $marker = $markers[$i];
          $bounds.extend($marker.getPosition());
        }
        // map.fitBounds($bounds);
        $called = true;
      }
    });
    // Simplebar element in map sidebar
    var simplebar = false;

    $.iucnSimplebarHighlight = _.throttle(
    function () {
      if(!simplebar) {
        simplebar = $.data($('[data-iucn-simplebar]')[0], 'simplebar');
      }
      $(simplebar.getScrollElement()).scrollTop(0);
      simplebar.flashScrollbar();
    }, 1000);

    $.iucnUpdateMapDetail = function(mapDetail) {
      var $mapDetails = $('#map-site-details');
      var $colLeft = $('#col-left');
      // $mapDetails.height($mapDetails.innerHeight());
      $mapDetails.fadeOut(0).html(mapDetail).fadeIn(300);
      $colLeft[0].scrollTop =  $colLeft[0].scrollHeight;
      $.iucnSimplebarHighlight();
    };

    $.iucnResetMapDetail = function() {
      var $mapDetails = $('#map-site-details');
      var $colLeft = $('#col-left');
      // $mapDetails.height('auto');
      $mapDetails.fadeOut(0).html(config.empty_placeholder).fadeIn(300);
      $colLeft[0].scrollTop =  0;
      window.scrollTo(0, 0);
      $.iucnSimplebarHighlight();
    };

    $.iucnResetAllMarkerIcons = function() {
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $icon = config.icons['icon' + $marker.customInfo.status_id];
        // Avoid some suble flickering
        $marker.setIcon($icon);
        // if ($icon.url != $marker.getIcon().url) {
        // }
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
      $('#edit-q')
        .prop('selectedIndex', 0)
        .trigger('chosen:updated');
      $.iucnResetMapDetail();
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
    $('a.conservation-rating').on('click', function() {
      $('#map-filters li').removeClass('active');
      $(this).parent().addClass('active');
      var $filter_status_id = $(this).data('filter');
      for(var $i = 0; $i < $markers.length; $i++) {
        var $marker = $markers[$i];
        var $visible = $filter_status_id === 'all'
            || parseInt($filter_status_id) === parseInt($marker.customInfo.status_id);
        $marker.setVisible($visible);
      }
      if ($filter_status_id === 'all') {
        $.resetMapPosition();
      }
      else {
        // Clear selection since this site might not exist in new filtering
        $.iucnResetAllMarkerIcons();
      }
      $.iucnClearSelection();
      return false;
    });

    /**
     * Handle the site selection from the drop-down.
     */
    $('#edit-q').on('change', function() {
      if ($(this).val() !== '0') {
        $.showAllMarkers(true);
        $('#map-filters li').removeClass('active');
        for (var $i = 0; $i < $markers.length; $i++) {
          var $marker = $markers[$i];
          if ($marker.customInfo.id == $(this).val()) {
            $.iucnUpdateMapDetail($marker.customInfo.render);
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
        $.iucnClearSelection();
      }
    });

    /**
     * Scrolldown button
     */
    $('#frontpage-scroll-down').on('click', function() {
      var $target = $($(this).data('target'));
      $("html, body").animate({ scrollTop: $target.offset().top + $target.outerHeight(true) }, 700);
    });

    // var lockMapScrollwheel = function() {
    //   // console.log('locked');
    //   map.setOptions({
    //     scrollwheel: false
    //   });
    // };
    // var unlockMapScrollwheel = function () {
    //   // console.log('unlocked');
    //   map.setOptions({
    //     scrollwheel: true
    //   });
    // };

    // $('.col-right')
    //   .on('mouseenter', function () {
    //     setTimeout(function () {
    //       unlockMapScrollwheel();
    //     }, 2000);
    //   })
    //   .on('mouseleave', function () {
    //     lockMapScrollwheel();
    //   });

    // lockMapScrollwheel();
    map.setOptions({
      minZoom: 2,
      gestureHandling: 'cooperative'
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
