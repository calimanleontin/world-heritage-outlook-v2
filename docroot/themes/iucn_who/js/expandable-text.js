;( function( $, window, document, undefined ) {
  "use strict";

    var pluginName = "expandableText",
        dataKey = "plugin_" + pluginName,
        defaults = {
          propertyName: "value"
        };

    var userAgent = navigator.userAgent.toLowerCase();
    var isAndroidChrome = /chrome/.test(userAgent) && /android/.test(userAgent);
    var isIOSChrome = /crios/.test(userAgent);
    var is_iPhone_or_iPad = (navigator.userAgent.match(/i(Phone|Pad)/i));
    var isMobile = isAndroidChrome || isIOSChrome || is_iPhone_or_iPad;

    // The actual plugin constructor
    var Plugin = function ( element, options ) {
      this.element = element;
      this.settings = $.extend( {}, defaults, options );
      this._defaults = defaults;
      this._name = pluginName;
      this.$content = $( this.element ).find('.js-expandable-text__content');
      this.$trigger = $( this.element ).find('.js-expandable-text__trigger');

      // Cache window dimensions
      this.windowWidth = window.innerWidth;
      this.windowHeight = window.innerHeight;

      this.init();

    }

    Plugin.prototype = {
      init: function() {
        var self = this;

        self.setMaxHeight();
        self.setMoreLinksVisibility();

        $( self.$trigger ).on('click', function() {
          self.toggle();
        });

          // handle resize events - throttled with underscore.js (optional - requires core/underscore be added as a dependency in .libraries.yml)
        $(window).on('resize', _.debounce((function() {
          self.setMaxHeight.call(self);
          if (isMobile) {
            if (self.windowWidth !== window.innerWidth && self.windowHeight !== window.innerHeight) {
              self.windowWidth = window.innerWidth;
              self.windowHeight = window.innerHeight;
              self.setMoreLinksVisibility.call(self);
            }
          }
          else {
            self.setMoreLinksVisibility.call(self);
          }
        }), 200));

      },
      setMaxHeight: function() {
        $( this.element ).css('max-height', $( this.$content ).outerHeight());
      },
      setMoreLinksVisibility: function() {
        this.close();
        if($( this.element ).outerHeight() < $( this.$content ).outerHeight()) {
          $( this.$trigger).fadeIn(200);
        }
        else {
          $( this.$trigger).fadeOut(200);
          this.open();
        }
      },
      toggle: function() {
        $( this.element ).toggleClass('open');
      },
      open: function() {
        $( this.element ).addClass('open');
      },
      close: function() {
        $( this.element ).removeClass('open');
      }
    };

    $.fn[pluginName] = function( options ) {
        var plugin = this.data(dataKey);

        // has plugin instantiated ?
        if (plugin instanceof Plugin) {
            // if have options arguments, call plugin.init() again
            if (typeof options !== 'undefined') {
                plugin.init(options);
            }
        } else {
            plugin = new Plugin(this, options);
            this.data(dataKey, plugin);
        }

        return plugin;
    };

    $('[data-expandable]').each(function() { $(this).expandableText(); });

} )( jQuery, window, document );
