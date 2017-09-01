(function($) {
    $('.twitter, .facebook-share, .linkedin').click(function(e) {
        e.preventDefault();
        var width  = 575,
            height = 400,
            left   = ($(window).width()  - width)  / 2,
            top    = ($(window).height() - height) / 2,
            url    = this.href,
            opts   = 'status=1' +
                ',width='  + width  +
                ',height=' + height +
                ',top='    + top    +
                ',left='   + left;
        var title;
        switch(this.className) {
            case 'facebook-share share':
                title = 'facebook_share';
                break;
            case 'twitter share':
                title = 'twitter_share';
                break;
            case 'linkedin share':
                title = 'linkedin_share';
                break;
        }
        window.open(url, title, opts);
    });
})(jQuery);
