/* exported showRequirements, checkPasswordsMatch, validateSignupForm, clearErrorMessages */
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

function clearErrorMessages() {
    $('#message_box').hide().find('#message_box_items').empty();
}

/**
 * Validates the signup form before submission to prevent field clearing on server errors.
 * @param {Array} acceptedEmails - List of accepted email domains
 * @param {Object} userIdRequirements - Object containing min_length and max_length for user IDs
 * @returns {boolean} - True if validation passes, false otherwise
 */
function validateSignupForm(acceptedEmails, userIdRequirements) {
    // If no validation data available, let server handle it
    if (!acceptedEmails || !userIdRequirements) {
        return true;
    }

    const email = $('input[name="email"]').val().trim();
    const userId = $('input[name="user_id"]').val().trim();
    const password = $('#password-input').val();
    const confirmPassword = $('#confirm-password-input').val();

    const errors = [];

    // Validate email format and domain
    if (email) {
        const emailParts = email.split('@');
        if (emailParts.length !== 2 || emailParts[0].length === 0 || emailParts[1].length === 0) {
            errors.push('Please enter a valid email address.');
        }
        else {
            const emailDomain = emailParts[1].toLowerCase();
            const acceptedLower = acceptedEmails.map((e) => e.toLowerCase());
            if (!acceptedLower.includes(emailDomain)) {
                errors.push('This email is not accepted.');
            }
        }
    }

    // Validate user ID length
    if (userId && userIdRequirements.min_length && userIdRequirements.max_length) {
        if (userId.length < userIdRequirements.min_length || userId.length > userIdRequirements.max_length) {
            errors.push('This user id does not meet the requirements.');
        }
    }

    // Check passwords match before submitting
    if (password && confirmPassword && password !== confirmPassword) {
        errors.push('Passwords do not match.');
    }

    // If any errors, display all and prevent submission
    if (errors.length > 0) {
        // Clear any existing messages first
        $('#message_box').hide();
        $('#message_box_items').empty();

        // Join errors with a space for cleaner display
        displayErrorMessage(errors.join(' '));
        return false;
    }

    return true;
}
