(function (Headroom) {
  // grab an element

  var myElement = document.querySelector("header.navbar");
  // construct an instance of Headroom, passing the element
  if(myElement) {
    var headroom  = new Headroom(myElement);
    // initialise
    headroom.init();
  }
}(Headroom));
