(function($) {
    $(function() {
        $('#publications-back-button').click(function() {
            window.location.href = document.referrer;
        });
    });
}(jQuery));
