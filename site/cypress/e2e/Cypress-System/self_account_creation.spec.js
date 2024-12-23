describe('Self account creation tests', () => {
    it('Basic account creation test', () => {
        cy.visit();
        cy.get('[data-testid="new-account-button"]').click();
        cy.get('[data-testid="email"]').type('test.email@gmail.com');
        cy.get('[data-testid="user-id"]').type('test_id');
        cy.get('[data-testid="given-name"]').type('GivenName');
        cy.get('[data-testid="family-name"]').type('FamilyName');
        cy.get('[data-testid="password"]').type('Password123!');
        cy.get('[data-testid="confirm-password"]').type('Password123!');
        cy.get('[data-testid="sign-up-button"]').click();
        cy.get('[data-testid="verification-code"]').type('00000000');
        cy.get('[data-testid="verify-email-button"').click();
        cy.get('[data-testid="popup-message"]').should('contain', 'You have successfully verified your email.');
        cy.login('test_id', 'Password123!');
        cy.get('body').should('contain', 'My Courses');
    });
});
