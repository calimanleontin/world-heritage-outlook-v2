(function($) {
  'use strict';

  // String.prototype.startsWith polyfill
  if (!String.prototype.startsWith) {
      String.prototype.startsWith = function(searchString, position){
          return this.substr(position || 0, searchString.length) === searchString;
      };
  }
  // String.prototype.includes polyfill
  if (!String.prototype.includes) {
      String.prototype.includes = function(search, start) {
          'use strict';
          if (typeof start !== 'number') {
              start = 0;
          }

          if (start + search.length > this.length) {
              return false;
          } else {
              return this.indexOf(search, start) !== -1;
          }
      };
  }

  $(function() {
    var $body = $(document.body);

    var blockBodyScroll = function () {
      $body.addClass('menu-open');
    };
    var unblockBodyScroll = function () {
      $body.removeClass('menu-open');
    };

    $('#navbar-collapse').on('show.bs.collapse', blockBodyScroll);
    $('#navbar-collapse').on('hide.bs.collapse', unblockBodyScroll);

    // Shows outlines if navigating with keyboard
    document.addEventListener('keydown', function(e) {
        if (e.keyCode === 9) {
        $body.addClass('show-focus-outlines');
        }
    });
    document.addEventListener('touchstart', function() {
        $body.removeClass('show-focus-outlines');
    });
    document.addEventListener('mousedown', function() {
        $body.removeClass('show-focus-outlines');
    });

    if (typeof IUCNVHChromeFix !== 'undefined') {
        var vhFixOptions = [
            {
                selector: '.path-frontpage #block-homepagemap .row.map-block-main', // Mandatory, CSS selector
                vh: 100,  // Mandatory, height in vh units
            },
        ];
        var vhFix = new IUCNVHChromeFix(vhFixOptions);
    }
    if($.ui && $.ui.autocomplete) {
        $.ui.autocomplete.prototype._resizeMenu = function () {
          var ul = this.menu.element;
          var $inputGroup = this.element.closest('.input-group');
          if($inputGroup.is(this.element.parent())) {
            ul.outerWidth($inputGroup.outerWidth());
          }
          else {
            ul.outerWidth(this.element.outerWidth());
          }
        }
    }

    var $document  = $(document);
    var $body = $('body');
    var $sidemenu = $('#sidemenu');
    var $sidemenuToggle = $('#sidemenu-toggle');
    var $modalBackdrop = $('.modal-backdrop');
    var iucnSidemenu = new IUCNSidemenu();

    $sidemenu.on('show.bs.dropdown', function () {
      $body.addClass('sidemenu-open');
      $modalBackdrop.show().addClass('in');
    })
    $sidemenu.on('hide.bs.dropdown', function () {
      $body.removeClass('sidemenu-open');
      $modalBackdrop.hide().removeClass('in');
    })

    $document.on('click', '#sidemenu', function (e) {
      e.stopPropagation();
    });
  });

}(jQuery));
