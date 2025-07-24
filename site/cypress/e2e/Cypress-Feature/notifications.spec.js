import { buildUrl } from '../../support/utils';

const verifyUpdateMessage = () => {
    cy.get('[data-testid="popup-message"]')
        .should('contain', 'Notification settings have been saved.')
        .get('[data-testid="remove-message-popup"]')
        .first()
        .click();
};

const verifyIndividualNotificationUpdate = (name, reload = false, state = {}) => {
    if (Object.keys(state).length === 0) {
        cy.get('[data-testid="checkbox-input"]').each(($el) => {
            const $elJQ = Cypress.$($el);
            const inputName = $elJQ.attr('name');
            state[inputName] = $elJQ.prop('checked');
        });
    }
    else {
        cy.log(`Skipping state init for: ${name}`);
    }

    cy.get(`input[data-testid="checkbox-input"][name="${name}"]`).then(($checkbox) => {
        const isDisabled = $checkbox.prop('disabled');
        if (!isDisabled) {
            cy.wrap($checkbox).click();
            verifyUpdateMessage();
        }
        else {
            cy.get('[data-testid="popup-message"]').should('not.exist');
        }
    });

    cy.get('[data-testid="checkbox-input"]').each(($el) => {
        const inputName = $el.attr('name');
        const isDisabled = $el.prop('disabled');

        cy.wrap($el).invoke('prop', 'checked').then((currentChecked) => {
            const previousChecked = state[inputName];

            if (inputName === name && !isDisabled) {
                expect(currentChecked).to.not.equal(previousChecked);
            }
            else {
                expect(currentChecked).to.equal(previousChecked);
            }

            // Persist the most recent state of the checkbox
            state[inputName] = currentChecked;
        });
    });

    if (!reload) {
        // Verify the updates for all actions persist after a full page reload
        cy.reload();
        verifyIndividualNotificationUpdate(name, true, state);
    }
    else if (!state['toggle']) {
        // Perform the opposite action
        state['toggle'] = true;
        verifyIndividualNotificationUpdate(name, false, state);
    }
};

const verifyBulkNotificationUpdates = (type, action, reload = false) => {
    // Verify the success message is displayed
    if (!reload) {
        verifyUpdateMessage();
    }

    const checkboxClass = `${type === 'notification' ? '.notification-checkbox' : '.email-checkbox'} [data-testid="checkbox-input"]`;
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
        verifyBulkNotificationUpdates(type, action, true);
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
        // Test subscribing to all site notifications
        cy.get('[data-testid="subscribe-all-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all notifications').click();
        verifyBulkNotificationUpdates('notification', 'subscribe');

        // Unsubscribe from all optional notifications
        cy.get('[data-testid="unsubscribe-all-optional-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="unsubscribe-all-optional-notifications"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional notifications').click();
        verifyBulkNotificationUpdates('notification', 'unsubscribe');

        // Reset notification settings
        cy.get('[data-testid="reset-notification-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset notification settings').click();
        verifyBulkNotificationUpdates('notification', 'reset');

        // Test subscribing to all emails
        cy.get('[data-testid="subscribe-all-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Subscribe to all emails').click();
        verifyBulkNotificationUpdates('email', 'subscribe');

        // Unsubscribe from all optional emails
        cy.get('[data-testid="unsubscribe-all-optional-emails"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Unsubscribe from all optional emails').click();
        verifyBulkNotificationUpdates('email', 'unsubscribe');

        // Reset email settings
        cy.get('[data-testid="reset-email-settings"]').should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', 'Reset email settings').click();
        verifyBulkNotificationUpdates('email', 'reset');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification settings for individual checkboxes', () => {
        cy.get('.notification-checkbox [data-testid="checkbox-input"]')
            .each(($el) => {
                const name = $el.attr('name');
                verifyIndividualNotificationUpdate(name);
            });
    });
});
