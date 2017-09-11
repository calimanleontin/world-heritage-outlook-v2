(function ($) {
  var $collapses = $('.node--type-site-assessment .collapse');
  $('.node--type-site-assessment').on('click', '.collapse-button',  function () {
    $collapses.collapse('hide');
  });
  $('.node--type-site-assessment').on('click', '.expand-button', function () {
    $collapses.collapse('show');
  });
}(jQuery));
