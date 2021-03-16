(function($) {

$(document).ready(function(){
    prepareShowEvent();

    // put functions into global namespace
    window.comcal_showEventPopup = showEventPopup;
});

function prepareShowEvent() {
    hideShowEvent();
    $('.show-event .comcal-close').click(function() {
        hideShowEvent();
    });
}

function hideShowEvent() {
    $('.comcal-modal-wrapper.show-event').css({"z-index": -200})
    $('.comcal-modal-wrapper.show-event').hide();
}

function displayShowEvent() {
    $('.comcal-modal-wrapper.show-event').css({ "z-index": 200 })
    $('.comcal-modal-wrapper.show-event').show(200);
    $('.comcal-modal-wrapper.show-event').scrollTop(0);
    $('.comcal-modal-wrapper.show-event #content').hide();
    $('.comcal-modal-wrapper.show-event #loading').addClass('pulse-animation');
}

function showEventPopup(e, eventId, preventDefault) {
    displayShowEvent();
    comcal_api_queryEventData(eventId, 'Display', function (result) {
        updateContent(result);
    }).done(function(){
        $('.comcal-modal-wrapper.show-event #loading').removeClass('pulse-animation');
        $('.comcal-modal-wrapper.show-event #content').show();
    });

    if (preventDefault) {
        e.preventDefault();
    }
}

function updateContent(json) {
    for (var key in json) {
        let target = $('.show-event-content #content #' + key);
        if (target[0] !== undefined) {
            if (key === 'categories') {
                let cats = '';
                for (let catIndex in json[key]) {
                    cats += ' ' + json[key][catIndex].html;
                }
                target.html(cats);
            } else {
                target.html(json[key]);
                if (key == 'url') {
                    target.attr('href', json[key]);
                }
            }
        }
    }
}

})(jQuery);