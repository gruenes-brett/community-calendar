(function($) {

$(document).ready(function(){
    prepareShowEvent();
});

function prepareShowEvent() {
    // $('.modal-wrapper.show-event').appendTo('#page-wrapper');
    hideShowEvent();
    $('.show-event .close').click(function() {
        hideShowEvent();
    });
    $('table.event td.title, table.event td.organizer').click(showEvent);
}

function hideShowEvent() {
    $('.modal-wrapper.show-event').css({"z-index": -200})
    $('.modal-wrapper.show-event').hide();
}

function showShowEvent() {
    $('.modal-wrapper.show-event').css({ "z-index": 200 })
    $('.modal-wrapper.show-event').show(200);
    $('.modal-wrapper.show-event').scrollTop(0);
    $('.modal-wrapper.show-event #content').hide();
    $('.modal-wrapper.show-event #loading').addClass('pulse-animation');
}

function findEventId(element) {
    let table = $(element).parents('table.event');
    return table.attr('eventId');
}

function showEvent(e) {
    let eventId = findEventId(e.currentTarget);
    showShowEvent();
    queryEventData(eventId, 'Display', function (result) {
        updateContent(result);
    }).done(function(){
        $('.modal-wrapper.show-event #loading').removeClass('pulse-animation');
        $('.modal-wrapper.show-event #content').show();
    });
}

function updateContent(json) {
    for (var key in json) {
        let target = $('.show-event-content #content #' + key);
        if (target[0] !== undefined) {
            target.html(json[key]);
            if (key == 'url') {
                target.attr('href', json[key]);
            }
        }
    }
}

})(jQuery);