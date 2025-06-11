/* exported confirmUnregister */
/* global showPopup */
function confirmUnregister() {
    $('.popup-form').css('display', 'none');
    const form = $('#unregister-user-form');
    showPopup('#unregister-user-form');
    form.find('.form-body').scrollTop(0);
}
