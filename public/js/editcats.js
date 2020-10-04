(function($) {

$(document).ready(function(){
    prepareEditCatsForm();
});

function prepareEditCatsForm() {
    hideEditCatsForm();
    $('button.editCategories').click(editCategories);
    $('.edit-cats-dialog .comcal-cancel').click(hideEditCatsForm);
    $('.edit-cats-dialog .comcal-close').click(hideEditCatsForm);
    $('form#editCategories').submit( submitEditCategories );
}

function showEditForm() {
    $('.comcal-modal-wrapper.edit-cats-dialog').css({"z-index": 200})
    $('.comcal-modal-wrapper.edit-cats-dialog').show(200);
    $('.comcal-modal-wrapper.edit-cats-dialog').scrollTop(0);
}

function editCategories(event) {
    event.preventDefault();
    showEditForm();
}

function hideEditCatsForm() {
    $('.comcal-modal-wrapper.edit-cats-dialog').css({"z-index": -200})
    $('.comcal-modal-wrapper.edit-cats-dialog').hide();
}

function submitEditCategories(event) {
    event.preventDefault();
    let form = $(this);
    __submitForm(form);
}
function __submitForm(form) {
    form.ajaxSubmit({
        success: function(response) {
            console.log(response);
            alert(response);
            location.reload();
        },
        error: function(response) {
            let text = response.responseText;
            console.error(text);
            alert(response);
            location.reload();
        },
    });
}

})(jQuery);