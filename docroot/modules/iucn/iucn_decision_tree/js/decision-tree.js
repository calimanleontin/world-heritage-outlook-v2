(function ($) {
  Drupal.behaviors.load_decision = {
    attach: function (context, settings) {
      $( document ).on( "click", "a[rel=load-decisions]", function() {
        var $decisions = $(this).data('decisions');
        var $container = $(this).data('container');
        var $container_element = $('#' + $(this).data('container'));

        $container_element.empty();
        $('a[data-container=' + $container + ']').removeClass('active');
        $link_element = $(this);
        $container_element.addClass('media--loading');
        $.ajax({
          url: '/decision_tree/nodes/' + $decisions,
          type: 'GET',
        }).done(function($data) {
          $container_element.hide().html($data).removeClass('media--loading').fadeIn();
          $link_element.addClass('active');
        });
        return false;
      });
    }
  };

}(jQuery));
