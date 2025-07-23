import { buildUrl } from '../../support/utils';

describe('Test cases revolving around notification settings', () => {
    beforeEach(() => {
        cy.login('student');
        cy.visit(buildUrl(['sample', 'notifications', 'settings']));
    });

    it('Should allow the notification settings page to be accessed', () => {
        cy.visit(buildUrl(['sample']));
        cy.get('[data-testid="sidebar"]')
            .contains('Notifications')
            .click();
        cy.get('[data-testid="notification-settings-button"]').click();
        cy.get('[data-testid="notification-settings-header"]').should('contain', 'Notification/Email Settings');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification settings', () => {
        // Test subscribing to all site notifications
        cy.get('[data-testid="subscribe-all-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all notifications').click();

        cy.get('[data-testid="popup-message"]').should('contain', 'Notification settings have been saved.');
        cy.get('.site-input [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.prop('checked')).to.be.true;
            });

        // Unsubscribe from all optional notifications
        cy.get('[data-testid="unsubscribe-all-optional-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional notifications').click();
        cy.get('.site-input [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                // TODO: twig template should set this to true
                if ($el.attr('data-default-checked') !== '1') {
                    expect($el.prop('checked')).to.be.false;
                }
            });

        // TODO: test reset notification settings (i.e., missing else block above)

        // Test subscribing to all emails
        cy.get('[data-testid="subscribe-all-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all emails').click();

        cy.get('[data-testid="popup-message"]').should('contain', 'Notification settings have been saved.');
        cy.get('.email-input [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.prop('checked')).to.be.true;
            });

        // Test unsubscribing from all site notifications
        // TODO: understand default checked
    });
});
