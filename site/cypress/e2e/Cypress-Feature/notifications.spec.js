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
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.notification-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.prop('checked')).to.be.true;
            });

        // Unsubscribe from all optional notifications
        cy.get('[data-testid="unsubscribe-all-optional-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional notifications').click();
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.notification-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                // Required notification checkboxes are disabled
                expect($el.prop('checked') === $el.prop('disabled')).to.be.true;
            });

        // Reset notification settings
        cy.get('[data-testid="reset-notification-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset notification settings').click();
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.notification-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.attr('data-default-checked') === 'true' ? $el.prop('checked') : !$el.prop('checked')).to.be.true;
            });

        // Test subscribing to all emails
        cy.get('[data-testid="subscribe-all-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all emails').click();
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.email-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.prop('checked')).to.be.true;
            });

        // Unsubscribe from all optional emails
        cy.get('[data-testid="unsubscribe-all-optional-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional emails').click();
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.email-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.prop('checked') === $el.prop('disabled')).to.be.true;
            });

        // Reset email settings
        cy.get('[data-testid="reset-email-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset email settings').click();
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();

        cy.get('.email-checkbox [data-testid="notification-checkbox-input"]')
            .should('have.length.greaterThan', 0)
            .each(($el) => {
                expect($el.attr('data-default-checked') === 'true' ? $el.prop('checked') : !$el.prop('checked')).to.be.true;
            });
    });
});
