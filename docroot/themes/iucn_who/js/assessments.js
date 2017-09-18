(function ($) {
  'use strict';

  var $collapses = $('.node--type-site-assessment .collapse');
  $('.node--type-site-assessment').on('click', '.collapse-button',  function () {
    $collapses.collapse('hide');
  });
  $('.node--type-site-assessment').on('click', '.expand-button', function () {
    $collapses.collapse('show');
  });

  var tabTarget = window.location.hash;
  // tabTarget = tabTarget.replace('#', '');

  // delete hash so the page won't scroll to it
  window.location.hash = '';


  $(document).ready(function () {

    var $body = $('body');
    var hashUpdate = function (hash) {
      if (hash.substr(0,1) == "#") {
        // var position = $(window).scrollTop();
        // location.replace("#" + hash.substr(1));
        // $(window).scrollTop(position);
        $body
          .attr('class', function(i, c) {
            return c.replace(/\S+-is-active-tab(^|\s)/g, hash.substr(1) + '-is-active-tab ');
          });
      }
    }

    // if (tabTarget !== '') {
    //   $('a[href="' + tabTarget + '"]').tab('show');
    //   if (tabTarget.substr(0,1) == "#") {
    //     $body.addClass(tabTarget.substr(1) + '-is-active-tab');
    //   }
    // }
    // else {
      var hash = $(".nav-tabs > li.active > a[data-toggle='tab']").attr("href");
      if(hash) {
        $body.addClass(hash.substr(1) + '-is-active-tab');
        $('a[href="' + hash + '"]').tab('show');
        // hashUpdate(hash);
      }
    // }
    $("a[data-toggle='tab']").on("shown.bs.tab", function (e) {
      var hash = $(e.target).attr("href");
      $('a[href="' + hash + '"]').tab('show');
      hashUpdate(hash);
    });

    var iucnSidemenu = new IUCNSidemenu();

    $('#assessment-tabs-mobile').on('click', 'li.active a', function() {
      iucnSidemenu.closeMenu();
    });

  });
}(jQuery));
