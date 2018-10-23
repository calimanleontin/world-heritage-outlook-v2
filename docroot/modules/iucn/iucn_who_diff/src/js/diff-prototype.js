import shave from 'shave';
window.shave = shave;

(function($) {

    Drupal.behaviors.shave = {
        attach: function (context, settings) {
            $('.diff-deletedline', context).each(function() {
                // console.log(this);
                // window.shave(this, 100);
                // var btn = $('<button class="js-shave-button">' + Drupal.t('Expand') + ' </button>',),
                //     textEl = this,
                //     textString = textEl.textContent;

                // if(!$(this).hasClass('shave-processed')) {
                //     $(this).append(btn);
                //     $(this).addClass('shave-processed');
                // }
            });
            // $('body').on('click', '.js-shave-button', function(e) {
            //       var hasShave = this.querySelector('#demo-text .js-shave');
            //       if (hasShave !== null) {
            //         this.textContent = textString;
            //         btn.textContent = 'Truncate Text ✁';
            //         return;
            //       }
            //       shave(this, 120);
            //       btn.textContent = 'Reset ⏎';
            //       return;

            // });
        }
    }

    Drupal.behaviors.fixedActions = {
        attach: function(context, settings) {

            $(function(){
                var $items = $('.paragraphs-actions .inner', context),
                inheritParentDims = function() {
                    $items.each(function() {
                        let $parent = $(this).parent();
                        let $parentHeight = $parent.height();
                        let $parentWidth = $parent.width();
                        // console.log($parentHeight);
                        // console.log($parentWidth);
                        $(this).height($parentHeight);
                        $(this).width($parentWidth);
                    });
                };

                inheritParentDims();
                $(window).once("bind-to-window").resize(inheritParentDims);
            });
        }
    }

    // Drupal.behaviors.horizontalScroll = {
    //     attach: function(context, settings) {

    //         $(function(){
    //             var $buttons = $('.slide-button', context);
    //             $buttons.each(function() {
    //                 var $container = $(this).closest('.responsive-wrapper');
    //                 $(this).height($container.height());
    //                 $(this).css({ top: $(this).parent().offsetTop });
    //             })

    //         });

    //         function sideScroll(element,direction,speed,distance,step){
    //             var scrollAmount = 0;
    //             var slideTimer = setInterval(function(){
    //                 if(direction == 'left'){
    //                     element.scrollLeft -= step;
    //                 } else {
    //                     element.scrollLeft += step;
    //                 }
    //                 scrollAmount += step;
    //                 if(scrollAmount >= distance){
    //                     window.clearInterval(slideTimer);
    //                 }
    //             }, speed);
    //         }

    //         $(context).on('click', '.slide-button', function() {
    //             var container = $(this).closest('.responsive-wrapper')[0],
    //                 scrollLeft = container.scrollLeft,
    //                 scrollWidth = container.scrollWidth;

    //             console.log(scrollLeft, scrollWidth );

    //             if($(this).hasClass('slide-right')) {
    //                 console.log(container);
    //                 if(scrollLeft == scrollWidth) {
    //                   $(this).addClass('hidden');
    //                 }
    //                 sideScroll(container,'right',25,100,10);
    //             }
    //             if($(this).hasClass('slide-left')) {
    //                 if(scrollLeft == 0) {
    //                   $(this).addClass('hidden');
    //                 }
    //                 sideScroll(container,'left',25,100,10);
    //             }
    //         });

    //     }
    // }

})(jQuery);
