(function ($) {
  Drupal.behaviors.load_decision = {
    attach: function (context, settings) {
      $( document ).on( "click", "a[rel=load-decisions]", function() {
        var $decisions = $(this).data('decisions');
        var $container = $(this).data('container');
        var $container_element = $('#' + $(this).data('container'));
        var $level = $(this).data('level');
        var $param = $(this).data('param');

        $container_element.empty();
        $('a[data-container=' + $container + ']').removeClass('active');
        $link_element = $(this);
        $container_element.addClass('media--loading');
        $.ajax({
          url: '/decision_tree/nodes/' + $decisions + '/level/' + $level ,
          type: 'GET',
        }).done(function($data) {
          $container_element.hide().html($data).removeClass('media--loading').fadeIn();
          $link_element.addClass('active');
          //updateDecisionTreeUrl($level, $param);
        });
        return false;
      });
    }
  };


  function updateDecisionTreeUrl($level, $param) {
    if (history.pushState) {
      $path_level = $level + 3;
      var path = window.location.pathname.split("/");
      var $u_param = '';
      if(path.length >= 4) {
        for (i = 4; i <= (path.length+1); i++) {
          if(i == $path_level){
            $u_param += '/' + $param;
            break;
          }else{
            $u_param += '/' + path[i];
          }
        }
      }
      var newurl = window.location.protocol + "//" + window.location.host + '/' + path[1] + '/' + path[2] + '/' + path[3]  + $u_param;
      window.history.pushState({path:newurl},'',newurl);
    }
  }
}(jQuery));
