const valid_given_name = 'GivenName';
const valid_user_id = 'good_id';
const valid_email = 'test.email@gmail.com';
const valid_password = 'Password123!';
const valid_family_name = 'FamilyName';
const incorrect_verification_code = '99999999';
const valid_verification_code = '00000000';

describe('Self account creation tests', () => {
    it('Test all paths of account creation', () => {
        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type('test_id');
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type(valid_password);
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
        cy.login('test_id', valid_password);
        cy.get('body').should('contain', 'My Courses');

        cy.logout();
        cy.get('[data-testid="new-account-button"]').click();

        // Not accepted email extension
        cy.get('[data-testid="email"]').type('test.email.bad@bad.com');
        cy.get('[data-testid="user-id"]').type(valid_user_id);
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type(valid_password);
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This email is not accepted');
        // Id too short
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type('1234');
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type(valid_password);
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');
        // Id too long
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type('123456789123456789123456789');
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type(valid_password);
        cy.get('[data-testid="confirm-password"]').type(valid_password);
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');
        // Password too short
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type(valid_user_id);
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type('pass!123');
        cy.get('[data-testid="confirm-password"]').type('pass!123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
        // Passwords don't match
        cy.get('[data-testid="remove-popup"]').click();
        cy.get('[data-testid="email"]').type(valid_email);
        cy.get('[data-testid="user-id"]').type(valid_user_id);
        cy.get('[data-testid="given-name"]').type(valid_given_name);
        cy.get('[data-testid="family-name"]').type(valid_family_name);
        cy.get('[data-testid="password"]').type('Password!123');
        cy.get('[data-testid="confirm-password"]').type('NotPassword!123');
        cy.get('[data-testid="confirm-password"]').blur();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match');
    });
});
