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
      config.icons[$i].scaledSize = new google.maps.Size(27, 27);
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

    $.iucnUpdateMapDetail = function(mapDetail) {
      var $mapDetails = $('#map-site-details');
      // $mapDetails.height($mapDetails.innerHeight());
      $mapDetails.fadeOut(0).html(mapDetail).fadeIn(300);
      // setTimeout(function() {
      //   $('.col-left').animate({ scrollTop: $mapDetails.innerHeight() });
      // }, 300);
    };

    $.iucnResetMapDetail = function() {
      var $mapDetails = $('#map-site-details');
      // $mapDetails.height('auto');
      $mapDetails.fadeOut(0).html(config.empty_placeholder).fadeIn(300);
      $('.col-left').animate({ scrollTop: 0 });
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
      }
    });

    /**
     * Scrolldown button
     */
    $('#frontpage-scroll-down').on('click', function() {
      var $target = $($(this).data('target'));
      $("html, body").animate({ scrollTop: $target.offset().top + $target.outerHeight(true) }, 700);
    });

    /**
     *  Prevent scroll on sidebar on desktop
     */
    var breakpoints = drupalSettings.responsive.breakpoints;
    var desktopBreakpoint = breakpoints['iucn_who.desktop'];

    var preventSidebarScroll = function () {
       // Requires dependency on core/matchmedia in .libraries.yml
       if (window.matchMedia(desktopBreakpoint).matches) {
        // console.log('desktop');
         $('[data-scroll="prevent"]').on('wheel', function (event) {
          var wheelEvent = event.originalEvent;
          var prevent = false;

          if (wheelEvent.deltaY > 0) { // scroll down
            prevent = 0 === this.scrollHeight- $(this).innerHeight() - $(this).scrollTop();
          } else { // scroll up
            prevent = 0 === $(this).scrollTop();
          }

          if (prevent) {
            event.preventDefault();
            event.stopPropagation();

            return false;
          }
        });
      }
      else {
        // console.log('mobile');
        $('[data-scroll="prevent"]').off('wheel');
      }
    };

    preventSidebarScroll();

    // handle resize events - throttled with underscore.js (optional - requires core/underscore be added as a dependency in .libraries.yml)
    $(window).on('resize', _.throttle(preventSidebarScroll, 200));

    var lockMapScrollwheel = function() {
      // console.log('locked');
      map.setOptions({
        scrollwheel: false
      });
    };
    var unlockMapScrollwheel = function () {
      // console.log('unlocked');
      map.setOptions({
        scrollwheel: true
      });
    };

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
