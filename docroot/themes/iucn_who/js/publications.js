(function($) {
    $(function() {
        // String.prototype.startsWith polyfill
        if (!String.prototype.startsWith) {
            String.prototype.startsWith = function(searchString, position){
              return this.substr(position || 0, searchString.length) === searchString;
          };
        }
        // String.prototype.includes polyfill
        if (!String.prototype.includes) {
          String.prototype.includes = function(search, start) {
            'use strict';
            if (typeof start !== 'number') {
              start = 0;
            }

            if (start + search.length > this.length) {
              return false;
            } else {
              return this.indexOf(search, start) !== -1;
            }
          };
        }
        var previousURI = document.referrer;
        if(!previousURI.startsWith(location.origin) || !previousURI.includes("/publications")) {
            var url = document.URL,
                previousURI=url.substring(0,url.lastIndexOf("/"));
        }
        $('a.publications-back-button-a').attr('href', previousURI);
    });
}(jQuery));

