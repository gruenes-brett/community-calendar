(function ($) {
    $(document).ready(function(){
        $('#comcal-copy-markdown').click(copyMarkdownToClipboard);
        $('button.scrollToToday').click(scrollToToday);
        $('button.scrollToTop').click(scrollToTop);
    });

function copyMarkdownToClipboard() {
    $('textarea#comcal-markdown').select();
    document.execCommand('copy');
}

function scrollToPosition(position) {
    $('html, body').delay(50).animate({scrollTop: position}, 500);
}

function scrollToToday() {
    let pos = $('.today').offset();
    if (pos !== undefined) {
        scrollToPosition(pos.top - 85);
    }
    $('button.scrollToToday').blur()
}

function scrollToTop() {
    scrollToPosition(0);
    $('button.scrollToTop').blur()
}

})(jQuery);