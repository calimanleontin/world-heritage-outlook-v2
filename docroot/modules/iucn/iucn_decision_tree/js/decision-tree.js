
(function ($) {
  // Tiny jQuery Plugin
  // by Chris Goodchild
  $.fn.exists = function(callback) {
    var args = [].slice.call(arguments, 1);

    if (this.length) {
      callback.call(this, args);
    }

    return this;
  };

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
          $this
            .addClass('active')
              .parent().addClass('active-inside')
            .end()
              .closest('.iucn-who-decision-tree-group')
                .siblings('.iucn-who-decision-tree-group').removeClass('active-group')
              .end()
              .addClass('active-group')
            .end();


          $container_element.find('.final-decision').exists(function() {
            $this.addClass('has-final-decision');
          });

        });
        return false;
      });
    }
  };

}(jQuery));
