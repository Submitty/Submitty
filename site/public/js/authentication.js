/* exported showRequirements, checkPasswordsMatch, validateSignupForm */
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

/**
 * Validates the signup form before submission to prevent field clearing on server errors.
 * @param {Array} acceptedEmails - List of accepted email domains
 * @param {Object} userIdRequirements - Object containing min_length and max_length for user IDs
 * @returns {boolean} - True if validation passes, false otherwise
 */
function validateSignupForm(acceptedEmails, userIdRequirements) {
    const email = $('input[name="email"]').val().trim();
    const userId = $('input[name="user_id"]').val().trim();

    // Validate email format and domain
    const emailParts = email.split('@');
    if (emailParts.length !== 2 || emailParts[0].length === 0 || emailParts[1].length === 0) {
        displayErrorMessage('Please enter a valid email address.');
        return false;
    }

    const emailDomain = emailParts[1].toLowerCase();
    const acceptedLower = acceptedEmails.map((e) => e.toLowerCase());
    if (!acceptedLower.includes(emailDomain)) {
        displayErrorMessage('This email is not accepted.');
        return false;
    }

    // Validate user ID length
    if (userId.length < userIdRequirements.min_length || userId.length > userIdRequirements.max_length) {
        displayErrorMessage('This user id does not meet the requirements.');
        return false;
    }

    // Check passwords match before submitting
    if ($('#password-input').val() !== $('#confirm-password-input').val()) {
        displayErrorMessage('Passwords do not match.');
        return false;
    }

    return true;
}
