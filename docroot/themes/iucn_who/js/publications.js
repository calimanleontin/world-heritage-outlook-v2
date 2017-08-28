(function($) {
    $(function() {
        var previousURI = document.referrer;
        if(!previousURI.startsWith(location.origin) || !previousURI.includes("/publications")) {
            var url = document.URL,
                previousURI=url.substring(0,url.lastIndexOf("/"));
        }
        $('a.publications-back-button-a').attr('href', previousURI);
    });
}(jQuery));

