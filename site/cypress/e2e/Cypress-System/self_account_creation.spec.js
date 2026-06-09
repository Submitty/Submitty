const valid_given_name = 'GivenName';
let valid_user_id = 'good_id';
let valid_email = 'valid.email@gmail.com';
const valid_password = 'Password123!';
const valid_family_name = 'FamilyName';
const incorrect_verification_code = '99999999';
const valid_verification_code = '00000000';

function inputData(email = valid_email, user_id = valid_user_id, password = valid_password, confirm_password = valid_password) {
    cy.get('[data-testid="email"]').type(email);
    cy.get('[data-testid="user-id"]').type(user_id);
    cy.get('[data-testid="given-name"]').type(valid_given_name);
    cy.get('[data-testid="family-name"]').type(valid_family_name);
    cy.get('[data-testid="password"]').type(password);
    cy.get('[data-testid="confirm-password"]').type(confirm_password);
}

function clearTextFields() {
    // clears all text fields in the Create Account form;
    // this function may or may not be needed, depending on if frontend validation
    // for passwords successfully prevents reload when password is incorrect
    // (event.preventDefault() doesn't always seem to do the trick)
    cy.get('[data-testid="email"]').clear();
    cy.get('[data-testid="user-id"]').clear();
    cy.get('[data-testid="given-name"]').clear();
    cy.get('[data-testid="family-name"]').clear();
    cy.get('[data-testid="password"]').clear();
    cy.get('[data-testid="confirm-password"]').clear();
}

describe('Self account creation tests', () => {
    it('Test all paths of account creation', () => {
        // create new randomized alphanumeric user id and email to limit interference of
        // current database contents on test (especially if this test is run multiple times)
        valid_user_id = Math.random().toString(36).substring(2, 8);
        valid_email = `${Math.random().toString(36).substring(2, 8)}@gmail.com`;

        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        // Not accepted email extension
        inputData('test.email.bad@bad.com', 'new_user_id');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This email is not accepted');
        // Id too short
        inputData(undefined, 'short');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');
        // Id too long
        inputData(undefined, '123456789123456789123456789');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');

        // Password too short
        inputData(undefined, undefined, 'pass!123', 'pass!123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
        clearTextFields(); // remove leftover inputs after frontend validation prevents sign up

        /*
            FIX ME! Currently the assertions below aren't accurate tests for the
            password complexity requirements, because these requirements are false by default in the configs
            and we don't yet have a method for changing configurations within Cypress tests.
            For reference, the relevant configs are:
                require_uppercase, require_lowercase, require_numbers, require_special_chars
        */
        /*
            // Password missing uppercase
            inputData(undefined, undefined, 'nouppercase#123', 'nouppercase#123');
            cy.get('[data-testid="sign-up-button"]').click();
            cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
            clearTextFields();
            // Password missing lowercase
            inputData(undefined, undefined, 'NOLOWERCASE#123', 'NOLOWERCASE#123');
            cy.get('[data-testid="sign-up-button"]').click();
            cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
            clearTextFields();
            // Password missing numbers
            inputData(undefined, undefined, 'NoNumbersHere!@', 'NoNumbersHere!@');
            cy.get('[data-testid="sign-up-button"]').click();
            cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
            clearTextFields();
            // Password missing special characters
            inputData(undefined, undefined, 'NoSpecialChar123', 'NoSpecialChar123');
            cy.get('[data-testid="sign-up-button"]').click();
            cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
            clearTextFields();
        */

        // Passwords don't match
        inputData(undefined, undefined, 'Password123!', 'NotPassword123!');
        cy.get('[data-testid="confirm-password"]').blur();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match');

        // Correct information
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();
        // Incorrect verification code
        cy.get('[data-testid="verification-code"]').type(incorrect_verification_code);
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'The verification code is not correct. Verify you entered the correct code or resend the verification email');
        // Correct verification code
        cy.get('[data-testid="verification-code"]').type(valid_verification_code);
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'You have successfully verified your email.');
        cy.login(valid_user_id, valid_password);
        cy.get('body').should('contain', 'My Courses');

        cy.logout();
    });
});
