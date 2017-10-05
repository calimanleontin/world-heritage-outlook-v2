(function (Headroom) {
  'use strict';
  // grab an element

  var myElement = document.querySelector("header.navbar");
  // construct an instance of Headroom, passing the element
  if(myElement) {
    var headroom  = new Headroom(myElement, {
      offset : myElement.offsetHeight
    });
    // initialise
    headroom.init();
  }
}(Headroom));
