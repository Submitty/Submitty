describe('Superuser Email All Functionality via Sidebar', () => {
    it('sends an email via Email All and verifies Email Status page', () => {
        cy.login('superuser');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        cy.url().should('include', '/superuser/email');
        cy.get('[data-testid="system-wide-email-title"]').should('contain', 'System Wide Email');

        const uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('[data-testid="email-subject"]').type(uniqueSubject);
        cy.get('[data-testid="email-content"]').type('This is a test email body.');
        cy.get('[data-testid="send-email"]').click();

        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();
        cy.get('body').should('contain', uniqueSubject);
    });
});
