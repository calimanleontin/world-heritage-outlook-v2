(function ($, Drupal) {
  Drupal.behaviors.goToButtons = {
    attach: function (context, settings) {
      $('.region-content').once('add-back-buttons').each(function () {
        var previousURI = document.referrer;
        var view_url = settings.iucn_site.go_to_buttons.view_url;
        var node_urls = settings.iucn_site.go_to_buttons.node_urls;

        $('a.go-to-button, a.back-button').addClass('hidden');
        if (!previousURI.startsWith(location.origin) || !previousURI.includes(view_url)) {
          $('a.go-to-button').attr('href', view_url).removeClass('hidden');
        }
        else {
          var good_previous_uri = 1;

          $.each(node_urls, function(idx, elem){
            if (previousURI.includes(elem)) {
              good_previous_uri = 0;
              return;
            }
          });
          if (good_previous_uri) {
            $('a.back-button').attr('href', previousURI).removeClass('hidden');
          }
          else {
            $('a.go-to-button').attr('href', view_url).removeClass('hidden');
          }
        }
      });
    }
  };
})(jQuery, Drupal);
