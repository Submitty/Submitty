const valid_given_name = 'GivenName';
let valid_user_id = 'good_id';
let valid_email = 'valid.email@gmail.com';
const valid_password = 'Password123!';
const valid_family_name = 'FamilyName';
const incorrect_verification_code = '99999999';
const valid_verification_code = '00000000';

function inputData(email = valid_email, user_id = valid_user_id, password = valid_password, confirm_password = valid_password) {
    cy.get('[data-testid="email"]').clear();
    cy.get('[data-testid="email"]').type(email);
    cy.get('[data-testid="user-id"]').clear();
    cy.get('[data-testid="user-id"]').type(user_id);
    cy.get('[data-testid="given-name"]').clear();
    cy.get('[data-testid="given-name"]').type(valid_given_name);
    cy.get('[data-testid="family-name"]').clear();
    cy.get('[data-testid="family-name"]').type(valid_family_name);
    cy.get('[data-testid="password"]').clear();
    cy.get('[data-testid="password"]').type(password);
    cy.get('[data-testid="confirm-password"]').clear();
    cy.get('[data-testid="confirm-password"]').type(confirm_password);
}

function clearTextFields() {
    // clears all text fields in the Create Account form; this is needed when
    // frontend password validation prevents a reload from clearing the form fields
    cy.get('[data-testid="email"]').clear();
    cy.get('[data-testid="user-id"]').clear();
    cy.get('[data-testid="given-name"]').clear();
    cy.get('[data-testid="family-name"]').clear();
    cy.get('[data-testid="password"]').clear();
    cy.get('[data-testid="confirm-password"]').clear();
}

describe('Self account creation tests', () => {
    before(() => {
        // create new randomized alphanumeric user id and email to limit interference of
        // current database contents on test (especially if this test is run multiple times)
        valid_user_id = Math.random().toString(36).substring(2, 8);
        valid_email = `${Math.random().toString(36).substring(2, 8)}@gmail.com`;
    });

    it('Test all paths of account creation', () => {
        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        // Not accepted email extension
        inputData('test.email.bad@bad.com', 'new_user_id');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This email is not accepted.');
        cy.get('[data-testid="email"]').should('have.value', 'test.email.bad@bad.com');
        cy.get('[data-testid="user-id"]').should('have.value', 'new_user_id');
        cy.get('[data-testid="given-name"]').should('have.value', valid_given_name);
        cy.get('[data-testid="family-name"]').should('have.value', valid_family_name);
        cy.get('[data-testid="password"]').should('have.value', '');
        cy.get('[data-testid="confirm-password"]').should('have.value', '');
        cy.get('[data-testid="remove-message-popup"').click(); // unremoved popups eventually clog the screen
        // Id too short
        inputData(undefined, 'short');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        // Id too long
        inputData(undefined, '123456789123456789123456789');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();

        // Password too short
        inputData(undefined, undefined, 'pass!123', 'pass!123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        clearTextFields(); // remove leftover inputs after frontend validation prevents sign up

        /*
            the following password tests will fail locally because the password complexity
            requirements are disabled by default and only enabled in CI
        */
        // Password missing uppercase
        inputData(undefined, undefined, 'nouppercase#123', 'nouppercase#123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        clearTextFields();
        // Password missing lowercase
        inputData(undefined, undefined, 'NOLOWERCASE#123', 'NOLOWERCASE#123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        clearTextFields();
        // Password missing numbers
        inputData(undefined, undefined, 'NoNumbersHere!@', 'NoNumbersHere!@');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        clearTextFields();
        // Password missing special characters
        inputData(undefined, undefined, 'NoSpecialChar123', 'NoSpecialChar123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        cy.get('[data-testid="remove-message-popup"').click();
        clearTextFields();

        // Passwords don't match
        inputData(undefined, undefined, 'Password123!', 'NotPassword123!');
        cy.get('[data-testid="confirm-password"]').blur();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match.');
        cy.get('[data-testid="remove-message-popup"').click();

        // Correct information
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();
        // Incorrect verification code
        cy.get('[data-testid="verification-code"]').type(incorrect_verification_code);
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'The verification code is not correct. Verify you entered the correct code or resend the verification email');
        cy.get('[data-testid="remove-message-popup"').click();
        // Correct verification code
        cy.get('[data-testid="verification-code"]').type(valid_verification_code);
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'You have successfully verified your email.');
        cy.get('[data-testid="remove-message-popup"').click();
        cy.login(valid_user_id, valid_password);
        cy.get('body').should('contain', 'My Courses');

        cy.logout();
    });
});
