const valid_given_name = 'GivenName';
const valid_user_id = 'good_id';
const valid_email = 'valid.email@gmail.com';
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

describe('Self account creation tests', () => {
    it('Test all paths of account creation', () => {
        cy.visit()
        cy.get('[data-testid="new-account-button"]').click();
        // Not accepted email extension
        inputData('test.email.bad@bad.com');
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
        // Passwords don't match
        inputData(undefined, undefined, 'Password123!', 'NotPassword123!')
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
