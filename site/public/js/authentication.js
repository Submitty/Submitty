/* exported showRequirements, checkPasswordsMatch, validateSignupForm  */
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

function validateSignupForm(acceptedEmails, userIdRequirements) {
    const email = $('input[name="email"]').val().trim();
    const userId = $('input[name="user_id"]').val().trim();

    // Validate email extension
    const emailParts = email.split('@');
    if (emailParts.length < 2 || !acceptedEmails.includes(emailParts[emailParts.length - 1])) {
        displayErrorMessage('This email is not accepted.');
        return false;
    }

    // Validate user ID length
    if (userId.length < userIdRequirements.min_length || userId.length > userIdRequirements.max_length) {
        displayErrorMessage('This user id does not meet the requirements.');
        return false;
    }

    return true;
}
