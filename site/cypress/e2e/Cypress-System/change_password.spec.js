const valid_given_name = 'GivenName';
let valid_user_id = 'good_id';
let valid_email = 'valid.email@gmail.com';
const valid_password = 'Password123!';
const valid_family_name = 'FamilyName';
const valid_verification_code = '00000000';

const alternative_valid_password = 'Other12!Pass';

function inputData(password = 'submitty-admin', confirm_password = password) {
    cy.get('#new_password').type(password);
    cy.get('#confirm_new_password').type(confirm_password);
}

function clearTextFields() {
    cy.get('#new_password').clear();
    cy.get('#confirm_new_password').clear();
}

describe('Change password test', () => {
    before(() => {
        // create new randomized alphanumeric user id and email to limit interference of
        // current database contents on test (especially if this test is run multiple times)
        valid_user_id = Math.random().toString(36).substring(2, 8);
        valid_email = `${Math.random().toString(36).substring(2, 8)}@gmail.com`;
        // create a new account because current accounts don't satisfy password requirements,
        // meaning we'd have to change the configs before reseting their password and continuing testing
        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type(valid_user_id);
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type(valid_password);
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();

        cy.get('[data-testid="verification-code"]').type(valid_verification_code);
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'You have successfully verified your email.');
        cy.login(valid_user_id, valid_password);
        cy.get('body').should('contain', 'My Courses');
    });

    it('Users can change their password', () => {
        cy.visit('/user_profile');
        cy.get('[data-testid="user-profile-change-password"]').click();

        // Password too short
        inputData('short');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields(); // remove leftover inputs after frontend validation prevents change password
        /*
            the following password tests will fail locally because the password complexity
            requirements are disabled by default and only enabled in CI
        */
        // Password missing uppercase
        inputData('nouppercase#123');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing lowercase
        inputData('NOLOWERCASE#123');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing numbers
        inputData('NoNumbersHere!@');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing special characters
        inputData('NoSpecialChar123');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Passwords don't match
        inputData('Password123!', 'NotPassword123!');
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match.');
        clearTextFields();

        // Valid, matching passwords
        inputData(alternative_valid_password);
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Updated password');

        // Can't log in with old password
        cy.logout();
        cy.login(valid_user_id, valid_password);
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Could not login using that user id or password');

        // Can log in with new password
        cy.login(valid_user_id, alternative_valid_password);
        cy.get('[data-testid="popup-message"]').should('contain.text', `Successfully logged in as ${valid_user_id}`);

        // Change password back
        cy.visit('/user_profile');
        cy.get('[data-testid="user-profile-change-password"]').click();
        inputData(valid_password);
        cy.get('[data-testid="change-password-form-submit-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Updated password');

        cy.logout();
    });
});
