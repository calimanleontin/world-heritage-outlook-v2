(function ($) {
  'use strict';

  var $collapses = $('.node--type-site-assessment .collapse');
  $('.node--type-site-assessment').on('click touchstart', '.collapse-button',  function () {
    $collapses.collapse('hide');
  });
  $('.node--type-site-assessment').on('click touchstart', '.expand-button', function () {
    $collapses.collapse('show');
  });

  // var tabTarget = window.location.hash;
  // tabTarget = tabTarget.replace('#', '');

  // delete hash so the page won't scroll to it
  // window.location.hash = '';


  $(document).ready(function () {

    var $body = $('body');
    // var hashUpdate = function (hash) {
    //   if (hash.substr(0,1) == "#") {
    //     // var position = $(window).scrollTop();
    //     // location.replace("#" + hash.substr(1));
    //     // $(window).scrollTop(position);
    //     $body
    //       .attr('class', function(i, c) {
    //         return c.replace(/\S+-is-active-tab(^|\s)/g, hash.substr(1) + '-is-active-tab ');
    //       });
    //   }
    // }

    // if (tabTarget !== '') {
    //   $('a[href="' + tabTarget + '"]').tab('show');
    //   if (tabTarget.substr(0,1) == "#") {
    //     $body.addClass(tabTarget.substr(1) + '-is-active-tab');
    //   }
    // }
    // else {
    // }

    var hash = $('.nav-tabs > li.active > [data-toggle="tab"]').attr('href');
    if(hash) {
      $('a[href="' + hash + '"]').tab('show');
      $body.addClass(hash.substr(1) + '-is-active-tab');
      // hashUpdate(hash);
    }
    $('[data-toggle="tab"]').on('click touchstart', function (e) {
      e.preventDefault();
      var hash = $(this).attr("href");
      $('a[href="' + hash + '"]').tab('show');
      $('body').attr('class', function(i, c) {
        return c.replace(/\S+-is-active-tab(^|\s)/g, hash.substr(1) + '-is-active-tab ');
      });
      // hashUpdate(hash);
    });

    var iucnSidemenu = new IUCNSidemenu();

    $('#assessment-tabs-mobile').on('click touchstart', '[data-toggle="tab"]', function() {
      iucnSidemenu.closeMenu();
    });

  });
}(jQuery));
