(function ($) {
  Drupal.behaviors.load_decision = {
    attach: function (context, settings) {
      $( document ).on( "click", "a[rel=load-decisions]", function() {
        var $this = $(this);
        var $decisions = $this.data('decisions');
        var $container = $this.data('container');
        var $container_element = $('#' + $this.data('container'));


        $container_element.empty();
        $('a[data-container=' + $container + ']').removeClass('active');
        $container_element.addClass('media--loading');
        $.ajax({
          url: '/decision_tree/nodes/' + $decisions,
          type: 'GET',
        }).done(function($data) {
          $container_element.hide().html($data).removeClass('media--loading').fadeIn();
          $this.addClass('active');
          $this.parent().addClass('active-inside');
          $this.closest('.iucn-who-decision-tree-group')
            .siblings('.iucn-who-decision-tree-group').removeClass('active-group')
            .end()
            .addClass('active-group');
        });
        return false;
      });
    }
  };

}(jQuery));
