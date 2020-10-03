(function ($) {
    $(document).ready(function(){
        $('#comcal-copy-markdown').click(copyMarkdownToClipboard);
    });

function copyMarkdownToClipboard() {
    $('textarea#comcal-markdown').select();
    document.execCommand('copy');
}

})(jQuery);