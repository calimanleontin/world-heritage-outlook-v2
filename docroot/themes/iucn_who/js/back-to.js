(function ($, Drupal) {
    Drupal.behaviors.goToButtons = {
        attach: function (context, settings) {
            var previousURI = document.referrer;
            var view_url = settings.iucn_who.go_to_buttons.view_url;
            var node_urls = settings.iucn_who.go_to_buttons.node_urls;
            $('a.go-to-button, a.back-button').addClass('hidden');
            if(!previousURI.startsWith(location.origin) || !previousURI.includes(view_url)) {
                $('a.go-to-button').attr('href', view_url).removeClass('hidden');
            }
            else {
                var good_previous_uri = 1;
                for (var i = 0; i < node_urls.length; i++) {
                  if (previousURI.includes(node_urls[i])) {
                    good_previous_uri = 0;
                    break;
                  }
                }
                if (good_previous_uri) {
                  $('a.back-button').attr('href', previousURI).removeClass('hidden');
                }
                else {
                  $('a.go-to-button').attr('href', view_url).removeClass('hidden');
                }
            }
        }
    };
})(jQuery, Drupal);