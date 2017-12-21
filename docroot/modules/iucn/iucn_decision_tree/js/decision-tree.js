(function ($) {
  Drupal.behaviors.load_decision = {
    attach: function (context, settings) {
      $( document ).on( "click", "a[rel=load-decisions]", function() {
        var $decisions = $(this).data('decisions');
        var $container = $(this).data('container');
        $('a[data-container=' + $container + ']').removeClass('active');
        $(this).addClass('active');
        $.ajax({
          url: '/decision_tree/nodes/' + $decisions,
          type: 'GET',
        }).done(function($data) {
          $('#' + $container).html($data);
        });
        return false;
      });
    }
  };
}(jQuery));
