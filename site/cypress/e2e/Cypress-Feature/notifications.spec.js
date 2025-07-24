import { buildUrl } from '../../support/utils';

const verifyNotificationSettings = (type, action, reload = false) => {
    // Verify the success message is displayed
    if (!reload) {
        // Verify the success message is displayed after a server response
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .click();
    }

    // Verify the checkbox state based on the action
    const checkboxClass = type === 'notification'
        ? '.notification-checkbox [data-testid="notification-checkbox-input"]'
        : '.email-checkbox [data-testid="notification-checkbox-input"]';
    cy.get(checkboxClass)
        .should('have.length.greaterThan', 0)
        .each(($el) => {
            switch (action) {
                case 'subscribe':
                    expect($el.prop('checked')).to.be.true;
                    break;
                case 'unsubscribe':
                    expect($el.prop('checked') === $el.prop('disabled')).to.be.true;
                    break;
                case 'reset':
                    expect($el.attr('data-default-checked') === 'true' ? $el.prop('checked') : !$el.prop('checked')).to.be.true;
                    break;
                default:
                    throw new Error(`Invalid action: ${action}`);
            }
        });

    if (!reload) {
        // Verify the updates persist after a full page reload
        cy.reload();
        verifyNotificationSettings(type, action, true);
    }
};

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
        verifyNotificationSettings('notification', 'subscribe');

        // Unsubscribe from all optional notifications
        cy.get('[data-testid="unsubscribe-all-optional-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional notifications').click();
        verifyNotificationSettings('notification', 'unsubscribe');

        // Reset notification settings
        cy.get('[data-testid="reset-notification-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset notification settings').click();
        verifyNotificationSettings('notification', 'reset');

        // Test subscribing to all emails
        cy.get('[data-testid="subscribe-all-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all emails').click();
        verifyNotificationSettings('email', 'subscribe');

        // Unsubscribe from all optional emails
        cy.get('[data-testid="unsubscribe-all-optional-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional emails').click();
        verifyNotificationSettings('email', 'unsubscribe');

        // Reset email settings
        cy.get('[data-testid="reset-email-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset email settings').click();
        verifyNotificationSettings('email', 'reset');
    });
});
