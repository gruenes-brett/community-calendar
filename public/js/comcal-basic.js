(function ($) {
    $(document).ready(function(){
        $('#comcal-copy-markdown').click(copyMarkdownToClipboard);
        $('button.scrollToToday').click(scrollToToday);
    });

function copyMarkdownToClipboard() {
    $('textarea#comcal-markdown').select();
    document.execCommand('copy');
}

/**
 * Implements the 'scroll up'-button behavior.
 */
function scrollToToday(delay_ms) {
    let pos = $('tr.today').offset();
    if (pos !== undefined) {
        // jQuery(window).scrollTop(pos.top - 40);
        $('html, body').delay(delay_ms).animate({scrollTop: pos.top - 85}, 500);
    }
    $('button.scrollToToday').blur()
}

})(jQuery);