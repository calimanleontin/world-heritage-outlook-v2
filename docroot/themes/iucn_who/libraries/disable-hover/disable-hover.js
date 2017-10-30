//  ----------------------------------------------------------------------
//  =Disable Hover on Scroll
//  http://www.thecssninja.com/javascript/pointer-events-60fps
//  ----------------------------------------------------------------------

var body = document.body,
    timer;

window.addEventListener('scroll', function() {
    clearTimeout(timer);

    if(!body.classList.contains('disable-hover')) {
        body.classList.add('disable-hover');
    }

    timer = setTimeout(function(){
        body.classList.remove('disable-hover');
    }, 500);
}, false);
