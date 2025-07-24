import { buildUrl } from '../../support/utils';

const verifyUpdateMessage = (exists = true) => {
    if (exists) {
        cy.get('[data-testid="popup-message"]')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .first()
            .click();
    }
    else {
        cy.get('[data-testid="popup-message"]').should('not.exist');
    }
};

const verifyIndividualNotificationUpdate = (name, reload = false, state = {}) => {
    if (Object.keys(state).length === 0) {
        // Initialize the state of all checkboxes before performing the initial action
        cy.get('input[data-testid="checkbox-input"]').each(($el) => {
            const $checkbox = Cypress.$($el);
            const inputName = $checkbox.attr('name');
            state[inputName] = $checkbox.prop('checked');
        });
    }

    cy.get(`input[data-testid="checkbox-input"][name="${name}"]`).then(($checkbox) => {
        const isDisabled = $checkbox.prop('disabled');

        if (!isDisabled) {
            cy.wrap($checkbox).click();
            verifyUpdateMessage();
        }
        else {
            verifyUpdateMessage(false);
        }
    });

    cy.get('[data-testid="checkbox-input"]').each(($el) => {
        const inputName = $el.attr('name');
        const isDisabled = $el.prop('disabled');

        cy.wrap($el).invoke('prop', 'checked').then((currentChecked) => {
            const previousChecked = state[inputName];

            if (inputName === name && !isDisabled) {
                // Inputs that are not mandatory should have been toggled
                expect(currentChecked).to.not.equal(previousChecked);
                // Persist the most recent state of the checkbox
                state[inputName] = currentChecked;
            }
            else {
                // Other inputs should remain unchanged
                expect(currentChecked).to.equal(previousChecked);
            }
        });
    });

    if (!reload) {
        // Verify the updates for all actions persist after a full page reload
        cy.reload();
        verifyIndividualNotificationUpdate(name, true, state);
    }
    else if (!state['opposite']) {
        // Perform the opposite action
        state['opposite'] = true;
        verifyIndividualNotificationUpdate(name, false, state);
    }
};

const verifyBulkNotificationUpdates = (identifier, message, type, action, reload = false) => {
    // Verify the success message is displayed
    if (!reload) {
        cy.get(`[data-testid="${identifier}"]`).should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', message).click();
        verifyUpdateMessage();
    }

    const classPrefix = `${type === 'notification' ? '.notification-checkbox' : '.email-checkbox'}`;
    cy.get(`${classPrefix} [data-testid="checkbox-input"]`)
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
        // Verify the updates persist after a full page reload
        cy.reload();
        verifyBulkNotificationUpdates(identifier, message, type, action, true);
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

    cy.get('[data-testid="all-self-registration"]').should(!enable ? 'not.be.checked' : 'be.checked');
    cy.logout();
};

describe('Test cases revolving around notification settings', () => {
    before(() => {
        toggleSelfRegistration(true);
    });

    beforeEach(() => {
        cy.login('student');
        cy.visit(buildUrl(['sample', 'notifications', 'settings']));
    });

    after(() => {
        toggleSelfRegistration(false);
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
        // Subscribe to all notifications
        verifyBulkNotificationUpdates('subscribe-all-notifications', 'Subscribe to all notifications', 'notification', 'subscribe');

        // Unsubscribe from all optional notifications (optional notifications are disabled by default)
        verifyBulkNotificationUpdates('unsubscribe-all-optional-notifications', 'Unsubscribe from all optional notifications', 'notification', 'unsubscribe');

        // Reset notification settings to defaults
        verifyBulkNotificationUpdates('reset-notification-settings', 'Reset notification settings', 'notification', 'reset');

        // Subscribe to all emails
        verifyBulkNotificationUpdates('subscribe-all-emails', 'Subscribe to all emails', 'email', 'subscribe');

        // Unsubscribe from all optional emails (optional emails are disabled by default)
        verifyBulkNotificationUpdates('unsubscribe-all-optional-emails', 'Unsubscribe from all optional emails', 'email', 'unsubscribe');

        // Reset email settings to defaults
        verifyBulkNotificationUpdates('reset-email-settings', 'Reset email settings', 'email', 'reset');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification settings for individual checkboxes', () => {
        cy.get('.notification-checkbox [data-testid="checkbox-input"]')
            .each(($el) => {
                // Verify check and uncheck actions for each individual checkbox
                verifyIndividualNotificationUpdate($el.attr('name'));
            });
    });
});
