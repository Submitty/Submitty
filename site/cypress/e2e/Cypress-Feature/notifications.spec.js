import { buildUrl } from '../../support/utils';

const verifyUpdateMessage = (exists = true, customMessage = 'Notification settings have been saved.') => {
    if (exists) {
        cy.get('[data-testid="popup-message"]')
            .should('be.visible')
            .should('contain', customMessage)
            .get('[data-testid="remove-message-popup"]')
            .first()
            .click();
    }
    else {
        cy.get('[data-testid="popup-message"]').should('not.exist');
    }
};

const verifyIndividualNotificationUpdates = (name, reload = false, state = {}) => {
    if (Object.keys(state).length === 0) {
        // Initialize the state of all checkboxes before performing the initial action
        cy.get('input[data-testid="checkbox-input"]').each(($el) => {
            const inputName = $el.attr('name');
            state[inputName] = $el.prop('checked');
        });
    }

    cy.get(`input[data-testid="checkbox-input"][name="${name}"]`).should('exist').then(($el) => {
        const isDisabled = $el.prop('disabled');

        if (!isDisabled) {
            cy.wrap($el).click();
        }

        // Expect a success message only if checkbox is not disabled (i.e., not mandatory)
        verifyUpdateMessage(!isDisabled);
    });

    cy.get('input[data-testid="checkbox-input"]').each(($el) => {
        const inputName = $el.attr('name');
        const isDisabled = $el.prop('disabled');
        const currentChecked = $el.prop('checked');
        const previousChecked = state[inputName];

        if (!isDisabled && name === inputName) {
            // Checkboxes that are not mandatory should have been toggled
            expect(currentChecked).to.not.equal(previousChecked);
            // Persist the most recent state of the checkbox
            state[inputName] = currentChecked;
        }
        else {
            // Other checkboxes should remain unchanged
            expect(currentChecked).to.equal(previousChecked);
        }
    });

    if (!reload) {
        // Verify the updates persist for all actions after a full page reload
        cy.reload().then(() => verifyIndividualNotificationUpdates(name, true, state));
    }
    else if (!state.reversed) {
        // Perform the opposite action at most once, restoring the original state
        state.reversed = true;
        cy.then(() => verifyIndividualNotificationUpdates(name, false, state));
    }
};

const verifyBatchNotificationUpdates = (identifier, message, type, action, reload = false) => {
    if (!reload) {
        // Perform the initial batch update action
        cy.get(`[data-testid="${identifier}"]`).should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', message).click();
        verifyUpdateMessage();
    }

    const selectorPrefix = `${type === 'notification' ? '.notification-checkbox' : '.email-checkbox'}`;
    cy.get(`${selectorPrefix} input[data-testid="checkbox-input"]`)
        .should('have.length.greaterThan', 0)
        .each(($el) => {
            switch (action) {
                case 'subscribe':
                    expect($el.prop('checked')).to.be.true;
                    break;
                case 'unsubscribe':
                    expect($el.prop('checked')).to.equal($el.prop('disabled'));
                    break;
                case 'reset':
                    expect($el.prop('checked')).to.equal($el.attr('data-default-checked') === 'true');
                    break;
                default:
                    throw new Error(`Invalid action: ${action}`);
            }
        });

    if (!reload) {
        // Verify the updates persist for all actions after a full page reload
        cy.reload().then(() => verifyBatchNotificationUpdates(identifier, message, type, action, true));
    }
};

const toggleSelfRegistration = (enable) => {
    cy.login('instructor');
    cy.visit(['sample', 'config']);

    if (enable) {
        cy.get('[data-testid="default-section-id"]').select('1');
        cy.get('[data-testid="all-self-registration"]').check();
    }
    else {
        cy.get('[data-testid="all-self-registration"]').uncheck();
    }

    cy.get('[data-testid="all-self-registration"]').should(enable ? 'be.checked' : 'not.be.checked');
    cy.logout();
};

const verifySyncNotificationSettings = () => {
    // Reset notification settings to ensure consistent state
    cy.get('[data-testid="reset-notification-settings"]').click();
    verifyUpdateMessage();

    // Enable two unique non-disabled notifications for testing
    cy.get('.notification-checkbox input[data-testid="checkbox-input"]:not(:disabled)')
        .then(($checkboxes) => {
            // Click first available non-disabled checkbox
            cy.wrap($checkboxes[0]).click();
            verifyUpdateMessage();

            // Click second available non-disabled checkbox
            cy.wrap($checkboxes[1]).click();
            verifyUpdateMessage();
        });

    // Test sync functionality
    cy.get('[data-testid="sync-notifications-button"]')
        .should('be.visible')
        .should('contain', 'Sync Notifications')
        .click();

    verifyUpdateMessage(true, 'Notification sync has been enabled');

    // Verify button text changes after sync
    cy.get('[data-testid="sync-notifications-button"]')
        .should('contain', 'Unsync Notifications');

    // Test unsync functionality
    cy.get('[data-testid="sync-notifications-button"]').click();

    verifyUpdateMessage(true, 'Notification sync has been disabled');

    cy.get('[data-testid="sync-notifications-button"]')
        .should('contain', 'Sync Notifications');
};

const verifySetDefaultNotificationSettings = () => {
    // Test set defaults functionality
    cy.get('[data-testid="set-defaults-button"]')
        .should('be.visible')
        .should('contain', 'Set as Default Settings')
        .click();

    verifyUpdateMessage(true, 'notification settings have been saved as your default');

    // Verify clear button appears after setting defaults
    cy.get('[data-testid="clear-defaults-button"]')
        .should('be.visible')
        .should('contain', 'Clear Default Settings');
};

const verifyClearDefaultNotificationSettings = () => {
    // Verify that defaults are currently set (button should be visible)
    cy.get('[data-testid="clear-defaults-button"]')
        .should('be.visible')
        .should('contain', 'Clear Default Settings');

    // Test clear defaults functionality
    cy.get('[data-testid="clear-defaults-button"]').click();

    verifyUpdateMessage(true, 'notification settings have been cleared');

    // Verify button is hidden after clearing defaults
    cy.get('[data-testid="clear-defaults-button"]').should('not.exist');
};

describe('Test cases revolving around notification/email settings', () => {
    before(() => {
        // Additional notification settings are displayed for self registration courses
        toggleSelfRegistration(true);
    });

    beforeEach(() => {
        cy.login('student');
        cy.visit(buildUrl(['sample', 'notifications', 'settings']));
    });

    after(() => {
        // Reset the self registration setting to its original state for the sample course
        toggleSelfRegistration(false);
    });

    it('Should allow the user to access the notification settings page from the landing course page', () => {
        cy.visit(buildUrl(['sample']));
        cy.get('[data-testid="sidebar"]')
            .contains('Notifications')
            .click();
        cy.get('[data-testid="notification-settings-button"]').click();
        cy.get('[data-testid="notification-settings-header"]').should('contain', 'Notification/Email Settings');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification/email settings', () => {
        verifyBatchNotificationUpdates('subscribe-all-notifications', 'Subscribe to all notifications', 'notification', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-notifications', 'Unsubscribe from all optional notifications', 'notification', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-notification-settings', 'Reset notification settings', 'notification', 'reset');

        verifyBatchNotificationUpdates('subscribe-all-emails', 'Subscribe to all emails', 'email', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-emails', 'Unsubscribe from all optional emails', 'email', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-email-settings', 'Reset email settings', 'email', 'reset');
    });

    it('Should allow the user to subscribe and unsubscribe to individual notification/email inputs', () => {
        cy.get('input[data-testid="checkbox-input"]')
            .each(($el) => verifyIndividualNotificationUpdates($el.attr('name')));
    });

    it('Should allow the user to sync notification settings to other courses', () => {
        verifySyncNotificationSettings();
    });

    it('Should allow the user to set default notification settings', () => {
        verifySetDefaultNotificationSettings();
    });

    it('Should allow the user to clear default notification settings', () => {
        verifySetDefaultNotificationSettings(); // Set defaults first
        verifyClearDefaultNotificationSettings();
    });
});
