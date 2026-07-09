/* exported showRequirements, checkPasswordsMatch, validatePasswordClientSide, checkSubmittedPassword  */
/* global displayErrorMessage */
function showRequirements(id_string) {
    $(`#${id_string}-helper`).toggle();
}

// checks if the password matches its confirmation, given the ids of the tags holding them
function checkPasswordsMatch(password_id_string, confirm_password_id_string) {
    if ($(`#${password_id_string}`).val() !== $(`#${confirm_password_id_string}`).val()) {
        $(`#${confirm_password_id_string}`).val('');
        displayErrorMessage('Passwords do not match.');
    }
}

function validatePasswordClientSide(password, requirements) {
    if (password.length < requirements.min_length || password.length > requirements.max_length) {
        return false;
    }
    if (requirements.require_uppercase && !/[A-Z]/.test(password)) {
        return false;
    }
    if (requirements.require_lowercase && !/[a-z]/.test(password)) {
        return false;
    }
    if (requirements.require_numbers && !/[0-9]/.test(password)) {
        return false;
    }
    if (requirements.require_special_chars && !/[^A-Za-z0-9]/.test(password)) {
        return false;
    }
    return true;
}

function checkSubmittedPassword(event, password_id_string, confirm_password_id_string, passwordRequirements) {
    // frontend validation, check that password matches with its confirmation, and the password is valid
    if ($(`#${password_id_string}`).val() !== $(`#${confirm_password_id_string}`).val()) {
        event.preventDefault();
        displayErrorMessage('Passwords do not match.');
        $(`#${confirm_password_id_string}`).val('');
        return;
    }
    if (!validatePasswordClientSide($(`#${password_id_string}`).val(), passwordRequirements)) {
        event.preventDefault();
        displayErrorMessage('Password does not meet the requirements.');
    }
}
