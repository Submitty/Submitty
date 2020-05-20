function userNameChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-username-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="user_name_change"]', form).val("");
    $("#user-firstname-change").focus();
}

function passwordChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#change-password-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="new_password"]', form).val("");
    $('[name="confirm_new_password"]', form).val("");
    $("#new_password").focus();
}

function getGeneralTimeZoneOptions() {

}

function getSpecificTimeZoneOptions(general_option) {

}

$(document).ready(function() {
    // Populate the general time zone selector box with options
});
