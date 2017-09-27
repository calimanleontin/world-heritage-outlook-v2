;(function ($, Drupal, drupalSettings, SimpleBar) {
  'use strict';

  // https://tc39.github.io/ecma262/#sec-array.prototype.find
  if (!Array.prototype.find) {
    Object.defineProperty(Array.prototype, 'find', {
      value: function(predicate) {
       // 1. Let O be ? ToObject(this value).
        if (this == null) {
          throw new TypeError('"this" is null or not defined');
        }

        var o = Object(this);

        // 2. Let len be ? ToLength(? Get(O, "length")).
        var len = o.length >>> 0;

        // 3. If IsCallable(predicate) is false, throw a TypeError exception.
        if (typeof predicate !== 'function') {
          throw new TypeError('predicate must be a function');
        }

        // 4. If thisArg was supplied, let T be thisArg; else let T be undefined.
        var thisArg = arguments[1];

        // 5. Let k be 0.
        var k = 0;

        // 6. Repeat, while k < len
        while (k < len) {
          // a. Let Pk be ! ToString(k).
          // b. Let kValue be ? Get(O, Pk).
          // c. Let testResult be ToBoolean(? Call(predicate, T, « kValue, k, O »)).
          // d. If testResult is true, return kValue.
          var kValue = o[k];
          if (predicate.call(thisArg, kValue, k, o)) {
            return kValue;
          }
          // e. Increase k by 1.
          k++;
        }

        // 7. Return undefined.
        return undefined;
      }
    });
  }

  /**
   *  Prevent scroll on sidebar on desktop
   */
  var breakpoints = drupalSettings.responsive.breakpoints;
  var desktopBreakpoint = breakpoints['iucn_who.desktop'];

  $.fn.preventBodyScroll = function () {
    var $parent = $(this).parent();
    // $parent.addClass('scroll-prevented-top').addClass('scroll-prevented-bottom');
    var prevent = false;
    var preventDirection;

    if(0 === this.scrollHeight- $(this).innerHeight() - $(this).scrollTop()) {
      $parent.addClass('scroll-prevented-bottom');
    }
    if(0 === $(this).scrollTop()) {
      $parent.addClass('scroll-prevented-top');
    }

     // Requires dependency on core/matchmedia in .libraries.yml
     if (window.matchMedia(desktopBreakpoint).matches) {
      // console.log('desktop');
       $(this).on('wheel', function (event) {
        var wheelEvent = event.originalEvent;
        var prevent = false;
        var preventDirection;
        // var $parent = $(this);

        if (wheelEvent.deltaY > 0) { // scroll down
          // console.log('scrollBottom: ' + (this.scrollHeight- $(this).innerHeight() - $(this).scrollTop()).toString() );
          prevent = 0 === this.scrollHeight- $(this).innerHeight() - $(this).scrollTop();
          preventDirection = 'bottom';
        } else { // scroll up
          // console.log('scrollTop: ' + $(this).scrollTop());
          prevent = 0 === $(this).scrollTop();
          preventDirection = 'top';
        }

        if (prevent) {
          event.preventDefault();
          event.stopPropagation();
          // console.log('prevented');
          $parent.addClass('scroll-prevented-' + preventDirection);
          return false;
        }
        $parent.removeClass('scroll-prevented-top').removeClass('scroll-prevented-bottom');
        // console.log('not prevented');
      });
    }
    else {
      // console.log('mobile');
      $(this).off('wheel');
    }

  };

  var $simplebarElements = $('[data-iucn-simplebar]');

  var simpleBar = new SimpleBar($simplebarElements[0], {
    autoHide: ($simplebarElements.data('iucn-simplebar') === 'visible' ? false : true)
  });

  $('[data-prevent-scroll] .simplebar-scroll-content').preventBodyScroll();

  // handle resize events - throttled with underscore.js (optional - requires core/underscore be added as a dependency in .libraries.yml)
  $(window).on('resize', _.debounce(
    function() {
      $('[data-prevent-scroll] .simplebar-scroll-content').preventBodyScroll();
    }, 200));

})(jQuery, Drupal, drupalSettings, SimpleBar);
