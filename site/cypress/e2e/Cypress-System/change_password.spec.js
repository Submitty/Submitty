const valid_password = 'Password123!';

function inputData(password = 'submitty-admin', confirm_password = password) {
    cy.get('[#new_password]').type(password);
    cy.get('[#confirm_new_password]').type(confirm_password);
}

function clearTextFields() {
    cy.get('[#new_password]').clear();
    cy.get('[#confirm_new_password]').clear();
}

describe('Change password test', () => {
    it('Users can change their password', () => {
        // default password length requirement is 12; therefore, we want to sign in as
        // an account with a password length >= 12 so we can reset it at the end of the test
        cy.login('submitty-admin');
        cy.visit('/user_profile');
        cy.get('[data-testid="Change password"]').click();

        // Password too short
        inputData('short');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields(); // remove leftover inputs after frontend validation prevents change password
        /*
            the following password tests will fail locally because the password complexity
            requirements are disabled by default and only enabled in CI
        */
        // Password missing uppercase
        inputData('nouppercase#123');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing lowercase
        inputData('NOLOWERCASE#123');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing numbers
        inputData('NoNumbersHere!@');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Password missing special characters
        inputData('NoSpecialChar123');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        clearTextFields();
        // Passwords don't match
        inputData('Password123!', 'NotPassword123!');
        cy.get('[data-testid="change-password-form-submit-button"]');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match.');
        clearTextFields();

        // TODO:
        // Valid, matching passwords
        // inputData(valid_password);
        // cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements.');
        // cy.get('[data-testid="remove-message-popup"').click();
        // Can't log in with old password
        // Can log in with new password
        // Change password back
    });
});
