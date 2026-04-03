/* exported showRequirements, checkPasswordsMatch, validatePasswordClientSide  */
/* global displayErrorMessage */
function showRequirements(id_string) {
    $(`#${id_string}-helper`).toggle();
}

function checkPasswordsMatch() {
    if ($('#password-input').val() !== $('#confirm-password-input').val()) {
        $('#confirm-password-input').val('');
        displayErrorMessage('Passwords do not match');
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
