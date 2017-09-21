(function ($, Drupal) {
    Drupal.behaviors.goToButtons = {
        attach: function (context, settings) {
            var previousURI = document.referrer;
            var view_url = settings.iucn_who.go_to_buttons.view_url;
            $('a.go-to-button, a.back-button').addClass('hidden');
            if(!previousURI.startsWith(location.origin) || !previousURI.includes(view_url) || previousURI.includes(view_url + '/')) {
                $('a.go-to-button').attr('href', view_url).removeClass('hidden');
            }
            else {
                $('a.back-button').attr('href', previousURI).removeClass('hidden');
            }
        }
    };
})(jQuery, Drupal);