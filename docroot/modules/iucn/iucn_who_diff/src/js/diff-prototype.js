import shave from 'shave';
window.shave = shave;

(function($) {
    Drupal.behaviors.shave = {
        attach: function (context, settings) {
            $('.diff-deletedline', context).each(function() {
                console.log(this);
                window.shave(this, 100);
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
})(jQuery);
