(function($) {

$(document).ready(function(){
    prepareEditCatsForm();
});

function prepareEditCatsForm() {
    hideEditCatsForm();
    $('button.editCategories').click(editCategories);
    $('input#cancel').click(hideEditCatsForm);
    $('.edit-cats-dialog .close').click(hideEditCatsForm);
}

function showEditForm() {
    $('.evtcal-modal-wrapper.edit-cats-dialog').css({"z-index": 200})
    $('.evtcal-modal-wrapper.edit-cats-dialog').show(200);
    $('.evtcal-modal-wrapper.edit-cats-dialog').scrollTop(0);
}

function editCategories(event) {
    event.preventDefault();
    showEditForm();
}

function hideEditCatsForm() {
    $('.evtcal-modal-wrapper.edit-cats-dialog').css({"z-index": -200})
    $('.evtcal-modal-wrapper.edit-cats-dialog').hide();
}

})(jQuery);