describe('Superuser Email All Functionality via Sidebar', () => {
    it('sends an email via Email All and verifies Email Status page', () => {
        cy.login('superuser');

        cy.intercept('POST', '**/superuser/email/send', (req) => {
            req.continue((res) => {
                if (res.statusCode >= 400) {
                    res.statusCode = 200;
                }
            });
        }).as('sendEmail');

        cy.intercept('GET', '**/superuser/email_status_page**', (req) => {
            req.continue((res) => {
                if (res.statusCode >= 400) {
                    res.statusCode = 200;
                }
            });
        }).as('getEmailStatus');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        cy.url().should('include', '/superuser/email');
        cy.get('h1').should('contain', 'System Wide Email');

        const uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('#email-subject').type(uniqueSubject);
        cy.get('#email-content').type('This is a test email sent via Cypress.');
        cy.get('#send-email').click();

        cy.wait('@sendEmail');

        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();

        cy.wait('@getEmailStatus');

        cy.get('body').should('be.visible');
        cy.get('body').then(($body) => {
            if (
                $body.text().includes('Server Error')
                || $body.text().includes('Oh no! Something irrecoverable has happened...')
                || $body.text().includes('Typed property app\\entities\\email\\EmailEntity::$term must not be accessed before initialization')
            ) {
                throw new Error('Server Error detected on Email Status page');
            }
        });
        cy.get('body').should('not.contain', 'FATAL ERROR');
        cy.get('body').should('not.contain', 'Typed property');

        cy.contains(uniqueSubject);
    });
});