describe('Self account creation tests', () => {
    it('Test all paths of account creation', () => {
        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        cy.get('[data-testid="email"]').type('test.email@gmail.com');
        cy.get('[data-testid="user-id"]').type('test_id');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password123!');
        cy.get('[data-testid="confirm-password"]').type('Password123!');
        cy.get('[data-testid="sign-up-button"]').click();
        // Bad code
        cy.get('[data-testid="verification-code"]').type('99999999');
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'The verification code is not correct. Verify you entered the correct code or resend the verification email');
        // Good Code
        cy.get('[data-testid="verification-code"]').type('00000000');
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'You have successfully verified your email.');
        cy.login('test_id', 'Password123!');
        cy.get('body').should('contain', 'My Courses');

        cy.logout();
        cy.get('[data-testid="new-account-button"]').click();

        // Bad email
        cy.get('[data-testid="email"]').type('test.email.bad@bad.com');
        cy.get('[data-testid="user-id"]').type('good_id');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password123!');
        cy.get('[data-testid="confirm-password"]').type('Password123!');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This email is not accepted');
        // Bad id too short
        cy.get('[data-testid="email"]').type('test.email.good@gmail.com');
        cy.get('[data-testid="user-id"]').type('1234');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password123!');
        cy.get('[data-testid="confirm-password"]').type('Password123!');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');
        // Bad id too long
        cy.get('[data-testid="email"]').type('test.email.good@gmail.com');
        cy.get('[data-testid="user-id"]').type('123456789123456789123456789');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password123!');
        cy.get('[data-testid="confirm-password"]').type('Password123!');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'This user id does not meet the requirements');
        // // Bad password too short
        cy.get('[data-testid="email"]').type('test.email.good@gmail.com');
        cy.get('[data-testid="user-id"]').type('good_id');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('pass!123');
        cy.get('[data-testid="confirm-password"]').type('pass!123');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Password does not meet the requirements');
        // // Bad passwords don't match
        cy.get('[data-testid="remove-popup"]').click();
        cy.get('[data-testid="email"]').type('test.email.good@gmail.com');
        cy.get('[data-testid="user-id"]').type('good_id');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password!123');
        cy.get('[data-testid="confirm-password"]').type('NotPassword!123');
        cy.get('[data-testid="confirm-password"]').blur();
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Passwords do not match');
    });
});
