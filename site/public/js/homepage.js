function userNameChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-username-form");
    form.css("display", "block");
    $('[name="user_name_change"]', form).val("");
    $("#user-firstname-change").focus();
}

function passwordChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#change-password-form");
    form.css("display", "block");
    $('[name="new_password"]', form).val("");
    $('[name="confirm_new_password"]', form).val("");
    $("#new_password").focus();
}