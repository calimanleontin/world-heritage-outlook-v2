;(function ($, Drupal, drupalSettings) {
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
  // $.fn.scrollSidesReached = function () {
  //   return this.each(function() {
  //     var element = this;
  //     var $parent = $(element).parent();

  //     if(0 === element.scrollHeight - $(element).innerHeight() - $(element).scrollTop()) {
  //       $parent.addClass('scroll-reached-bottom');
  //     }
  //     if(0 === $(element).scrollTop()) {
  //       $parent.addClass('scroll-reached-top');
  //     }
  //     $(element).on('wheel', function (event) {
  //       var wheelEvent = event.originalEvent;
  //       var reachedBottom = false;
  //       var reachedTop = false;
  //       var preventDirection;

  //       if($(element).hasScrollBar()) {
  //         console.log('hasScrollBar');
  //       }
  //       if (wheelEvent.deltaY > 0) { // scroll down
  //         reachedBottom = 0 === this.scrollHeight - $(this).innerHeight() - $(this).scrollTop();
  //         if (reachedBottom) $parent.addClass('scroll-reached-bottom');
  //       } else { // scroll up
  //         reachedTop = 0 === $(this).scrollTop();
  //         if (reachedTop) $parent.addClass('scroll-reached-top');
  //       }
  //       if(!reachedTop && !reachedBottom) {
  //         $parent.removeClass('scroll-reached-top').removeClass('scroll-reached-bottom');
  //       }
  //     });
  //   });
  // };
  /**
   *  Prevent scroll on sidebar on desktop
   */
  var breakpoints = drupalSettings.responsive.breakpoints;
  var desktopBreakpoint = breakpoints['iucn_who.desktop'];

  $.fn.preventBodyScroll = function () {
    return this.each(function() {
      var element = this;
      var $parent = $(element).parent();
      var prevent = false;

       // Requires dependency on core/matchmedia in .libraries.yml
       if (window.matchMedia(desktopBreakpoint).matches) {
         $(element).on('wheel', function (event) {
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
        $(this).off('wheel');
      }
    });
  };

  if(SimpleBar) {
    var $simplebarElements = $('[data-iucn-simplebar]');
    $simplebarElements.each(function() {
      var simpleBarElement = new SimpleBar(this, {
        autoHide: ($(this).data('iucn-simplebar') === 'visible' ? false : true)
      });
      $.data(this, 'simplebar', simpleBarElement);
      simpleBarElement.flashScrollbar();
    });
  }

  $('[data-prevent-scroll]').preventBodyScroll();

})(jQuery, Drupal, drupalSettings);
