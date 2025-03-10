describe('Superuser Email All Functionality via Sidebar', () => {
    it('sends an email via Email All and verifies Email Status page', () => {
        cy.login('superuser');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        cy.url().should('include', '/superuser/email');
        cy.get('h1').should('contain', 'System Wide Email');

        const uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('#email-subject').type(uniqueSubject);
        cy.get('#email-content').type('This is a test email sent via Cypress.');
        cy.get('#send-email').click();

        cy.wait(1000);

        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();

        cy.wait(1000);

        cy.contains(uniqueSubject);
    });
});