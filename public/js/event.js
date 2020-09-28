
function queryEventData(eventId, suffix, success) {
    return jQuery.get(
        '/wp-json/comcal/v1/event' + suffix + '/' + eventId,
        function (result) {
            success(result);
        }).fail(function (result) {
            console.log(result);
            alert(result.responseJSON.message);
        });
}

(function ($) {
    $(document).ready(function(){
        $('button.scrollToToday').click(scrollToToday);
    });

    function scrollToToday(delay_ms) {
        let pos = jQuery('tr.today').offset();
        if (pos !== undefined) {
            // jQuery(window).scrollTop(pos.top - 40);
            jQuery('html, body').delay(delay_ms).animate({scrollTop: pos.top - 85}, 500);
        }
    }
})(jQuery);