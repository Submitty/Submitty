/* exported showRequirements, checkPasswordsMatch, validateSignupForm, clearErrorMessages, validatePasswordClientSide, checkSubmittedPassword */
/* exported showRequirements, checkPasswordsMatch, validateSignupForm, clearErrorMessages */
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

function clearErrorMessages() {
    $('#messages .inner-message.alert-error').remove();
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

    clearErrorMessages();

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
