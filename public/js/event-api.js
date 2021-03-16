
(function ($) {

$(document).ready(function(){
    // put functions into global namespace
    window.comcal_api_queryEventData = queryEventData;
});

function queryEventData(eventId, suffix, success) {
    return $.get(
        '/wp-json/comcal/v1/event' + suffix + '/' + eventId,
        function (result) {
            success(result);
        }).fail(function (result) {
            console.log(result);
            alert(result.responseJSON.message);
        });
}

})(jQuery);